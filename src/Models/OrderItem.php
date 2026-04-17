<?php
namespace App\Models;

class OrderItem
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(int $request_id, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO order_items (request_id, meal_id, quantity, payment_method, custom_price)
             VALUES (:request_id, :meal_id, :quantity, :payment_method, :custom_price)
             RETURNING id"
        );
        $stmt->execute([
            ':request_id'    => $request_id,
            ':meal_id'       => $data['meal_id'] ?? null,
            ':quantity'      => $data['quantity'] ?? 1,
            ':payment_method'=> $data['payment_method'] ?? '自付',
            ':custom_price'  => $data['custom_price'] ?? null,  // only for 其他 category
        ]);
        return (int)$stmt->fetchColumn();
    }
}
