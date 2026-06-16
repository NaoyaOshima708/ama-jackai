<?php
declare(strict_types=1);

/**
 * Ollama 埋め込み + SQLite による軽量 RAG（同一 session_id 内の過去やり取りを検索）
 */
final class RagStore
{
    private PDO $pdo;
    private string $ollamaBase;
    private string $embedModel;

    public function __construct(array $config)
    {
        $this->ollamaBase = rtrim($config['ollama_base_url'] ?? 'http://127.0.0.1:11434', '/');
        $this->embedModel = $config['ollama_embed_model'] ?? 'nomic-embed-text';

        $dir = dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('data ディレクトリを作成できません。権限を確認してください。');
            }
        }

        $dbPath = $dir . '/rag.sqlite';
        $this->pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->pdo->exec('PRAGMA journal_mode=WAL;');
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS chunks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  session_key TEXT NOT NULL,
  body TEXT NOT NULL,
  embedding TEXT NOT NULL,
  created_at INTEGER NOT NULL
);
SQL);
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_chunks_session ON chunks(session_key);');
    }

    public function embed(string $text): ?array
    {
        $text = trim(mb_substr($text, 0, 8000));
        if ($text === '') {
            return null;
        }

        $url = $this->ollamaBase . '/api/embeddings';
        $payloads = [
            json_encode(['model' => $this->embedModel, 'prompt' => $text], JSON_UNESCAPED_UNICODE),
            json_encode(['model' => $this->embedModel, 'input' => $text], JSON_UNESCAPED_UNICODE),
        ];

        $data = null;
        foreach ($payloads as $payload) {
            if ($payload === false) {
                continue;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response === false || !is_string($response)) {
                continue;
            }

            $decoded = json_decode($response, true);
            if (is_array($decoded) && isset($decoded['embedding']) && is_array($decoded['embedding'])) {
                $data = $decoded;
                break;
            }
        }

        if ($data === null) {
            return null;
        }

        $vec = [];
        foreach ($data['embedding'] as $v) {
            $vec[] = (float) $v;
        }

        return $vec === [] ? null : $vec;
    }

    /**
     * @param array<float> $queryVec
     */
    public function searchContext(string $sessionKey, array $queryVec, int $topK, int $maxScan): string
    {
        $topK = max(1, min(12, $topK));
        $maxScan = max(50, min(5000, $maxScan));

        $stmt = $this->pdo->prepare(
            'SELECT id, body, embedding FROM chunks WHERE session_key = :sk ORDER BY id DESC LIMIT :lim'
        );
        $stmt->bindValue(':sk', $sessionKey, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $maxScan, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scored = [];
        foreach ($rows as $row) {
            $embJson = $row['embedding'] ?? '';
            $emb = json_decode((string) $embJson, true);
            if (!is_array($emb)) {
                continue;
            }
            $ev = [];
            foreach ($emb as $x) {
                $ev[] = (float) $x;
            }
            if (count($ev) !== count($queryVec)) {
                continue;
            }
            $sim = self::cosineSimilarity($queryVec, $ev);
            $scored[] = ['sim' => $sim, 'body' => (string) ($row['body'] ?? '')];
        }

        usort($scored, static fn ($a, $b) => $b['sim'] <=> $a['sim']);
        $picked = array_slice($scored, 0, $topK);
        $lines = [];
        foreach ($picked as $p) {
            if ($p['body'] !== '' && $p['sim'] > 0.15) {
                $lines[] = $p['body'];
            }
        }

        if ($lines === []) {
            return '';
        }

        return implode("\n---\n", $lines);
    }

    /**
     * @param array<float> $vec
     */
    public function store(string $sessionKey, string $body, array $vec): void
    {
        $body = trim(mb_substr($body, 0, 16000));
        if ($body === '' || $vec === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO chunks (session_key, body, embedding, created_at) VALUES (:sk, :body, :emb, :ts)'
        );
        $stmt->execute([
            ':sk' => $sessionKey,
            ':body' => $body,
            ':emb' => json_encode($vec, JSON_THROW_ON_ERROR),
            ':ts' => time(),
        ]);
    }

    /**
     * @param array<float> $a
     * @param array<float> $b
     */
    private static function cosineSimilarity(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        $den = sqrt($na) * sqrt($nb);
        if ($den < 1e-12) {
            return 0.0;
        }

        return $dot / $den;
    }
}

function rag_sanitize_session_id(?string $id): ?string
{
    if ($id === null || $id === '') {
        return null;
    }
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
        return null;
    }

    return strtolower($id);
}
