<?php
namespace mia\miagroup\Service;

use mia\miagroup\Model\Order as OrderModel;

class Order extends \FS_Service {
    
    public $orderModel;
    
    public function __construct() {
        $this->orderModel = new OrderModel();
    }
    
    //根据订单编号获取订单信息（订单状态为已完成,且完成时间15天内的才可以发布口碑！）
    public function getOrderInfo($orderCode){
        $orderInfo = $this->orderModel->getOrderInfoByOrderCode($orderCode);
        return $this->succ($orderInfo);
    }
    

}