<?php
namespace App\Models;

class OrderDetail
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(string $order_no, array $data): int
    {
        $sql = 'INSERT INTO order_details (order_no, item_id, quantity, price_per_unit, payment_method, subtotal) VALUES (:order_no, :item_id, :quantity, :price_per_unit, :payment_method, :subtotal)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':order_no' => $order_no,
            ':item_id' => $data['item_id'] ?? null,
            ':quantity' => $data['quantity'] ?? 0,
            ':price_per_unit' => $data['price_per_unit'] ?? 0,
            ':payment_method' => $data['payment_method'] ?? '自付',
            ':subtotal' => $data['subtotal'] ?? 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
