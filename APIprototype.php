<?php
interface IOrderService {
    function createOrder(User $user, array $items): Order;
    function getOrderStatus(int $orderId): string;
}

class OrderService implements IOrderService {
    private $db;
    private $logisticsService;

    public function __construct(PDO $db, ILogisticsService $logisticsService) {
        $this->db = $db;
        $this->logisticsService = $logisticsService;
    }

    public function createOrder(User $user, array $items): Order {        
        $stmt = $this->db->prepare("INSERT INTO orders (user_id, status, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user->getId(), 'created']);
        $orderId = $this->db->lastInsertId();

        foreach ($items as $item) {
            $stmt = $this->db->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$orderId, $item['product_id'], $item['quantity']]);
        }
        
        $this->logisticsService->registerDelivery($orderId);
        
        return new Order($orderId, $user, $items);
    }

    public function getOrderStatus(int $orderId): string {       
        $status = $this->logisticsService->getDeliveryStatus($orderId);
        $stmt = $this->db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $orderId]);

        return $status;
    }
}

interface ILogisticsService {
    function registerDelivery(int $orderId): void;
    function getDeliveryStatus(int $orderId): string;
}

class LogisticsService implements ILogisticsService {
    private $apiUrl;

    public function __construct(string $apiUrl) {
        $this->apiUrl = $apiUrl;
    }

    public function registerDelivery(int $orderId): void {
        $response = file_get_contents("$this->apiUrl/delivery/register?order_id=$orderId");
        if ($response !== 'OK') {
            throw new Exception('Error registering delivery');
        }
    }

    public function getDeliveryStatus(int $orderId): string {
        $response = file_get_contents("$this->apiUrl/delivery/status?order_id=$orderId");
        return $response;
    }
}