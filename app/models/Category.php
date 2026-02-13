<?php

declare(strict_types=1);

class Category
{
    public static function all(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name');
        return $stmt->fetchAll();
    }

    public static function allWithTotals(PDO $pdo, ?string $categoryColumn): array
    {
        if ($categoryColumn) {
            $stmt = $pdo->query(
                'SELECT categories.id, categories.name, COUNT(products.id) AS total_products
                 FROM categories
                 LEFT JOIN products ON products.' . $categoryColumn . ' = categories.id
                 GROUP BY categories.id, categories.name
                 ORDER BY categories.name'
            );
            return $stmt->fetchAll();
        }

        $stmt = $pdo->query('SELECT id, name, 0 AS total_products FROM categories ORDER BY name');
        return $stmt->fetchAll();
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(PDO $pdo, string $name): int
    {
        $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
        $stmt->execute([':name' => $name]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(PDO $pdo, int $id, string $name): void
    {
        $stmt = $pdo->prepare('UPDATE categories SET name = :name WHERE id = :id');
        $stmt->execute([':name' => $name, ':id' => $id]);
    }

    public static function delete(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
