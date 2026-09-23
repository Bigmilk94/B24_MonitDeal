<?php

declare(strict_types=1);

namespace App\Bitrix24;

use App\Bitrix24\Model\Portal;
use RuntimeException;

/**
 * Stores installed Bitrix24 portals (OAuth tokens + per-portal settings) in
 * a single JSON file — no database engine to install/configure. Reads and
 * writes are flock()'d so concurrent PHP-FPM workers don't corrupt it.
 *
 * This is the entire "multi-tenant" state of the app: one row per portal
 * that has installed it, keyed by Bitrix24's member_id.
 */
final class PortalRepository
{
    public function __construct(private readonly string $storageFile)
    {
    }

    public function find(string $memberId): ?Portal
    {
        $all = $this->readAll();

        return isset($all[$memberId]) ? Portal::fromArray($all[$memberId]) : null;
    }

    public function save(Portal $portal): void
    {
        $this->withLock(function (array $all) use ($portal): array {
            $all[$portal->memberId] = $portal->toArray();

            return $all;
        });
    }

    public function delete(string $memberId): void
    {
        $this->withLock(function (array $all) use ($memberId): array {
            unset($all[$memberId]);

            return $all;
        });
    }

    /**
     * @return list<Portal>
     */
    public function all(): array
    {
        return array_values(array_map(
            static fn (array $row): Portal => Portal::fromArray($row),
            $this->readAll(),
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readAll(): array
    {
        if (!is_file($this->storageFile)) {
            return [];
        }

        $contents = file_get_contents($this->storageFile);
        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $data = json_decode($contents, true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param callable(array<string, array<string, mixed>>): array<string, array<string, mixed>> $mutator
     */
    private function withLock(callable $mutator): void
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Nie można utworzyć katalogu na dane: {$dir}");
        }

        $handle = fopen($this->storageFile, 'c+');
        if ($handle === false) {
            throw new RuntimeException("Nie można otworzyć pliku danych: {$this->storageFile}");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Nie można zablokować pliku danych do zapisu.');
            }

            $size = fstat($handle)['size'] ?? 0;
            $contents = $size > 0 ? fread($handle, $size) : '';
            $data = json_decode((string) $contents, true);
            $all = is_array($data) ? $data : [];

            $all = $mutator($all);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
