<?php
namespace mia\miagroup\Service;

use mia\miagroup\Model\Item as ItemModel;


class Item extends \mia\miagroup\Lib\Service {
    public $itemModel;
    public function __construct() {
        $this->itemModel = new ItemModel();
    }
    
    /**
     * 获取商品的关联商品或者套装单品
     */
    public function getRelateItemById($item_id)
    {
        $item_info = $this->itemModel->getBatchItemByIds([$item_id])[$item_id];
        if (empty($item_info)) {
            return $this->succ(array());
        }
        $item_ids = array();
        // 如果是单品，直接取商品口碑
        if ($item_info['is_spu'] == 0) {
            if (!empty($item_info['relate_flag'])) {
                $related_items = $this->itemModel->getBatchItemByFlags([$item_info['relate_flag']]);
                if (!empty($related_items)) {
                    foreach ($related_items as $v) {
                        $item_ids[] = $v['id'];
                    }
                }
            }
        } elseif ($item_info['is_spu'] == 1 && $item_info['spu_type'] == 1) { // 是单品套装的情况
            // 根据套装id获取套装的商品
            $spu_item_ids = $this->itemModel->getItemBySpuId($item_id);
            // 根据套装的商品，获取商品的所有套装，实现套装和套装的互通
            $item_id_array = $this->itemModel->getSpuByItemId($spu_item_ids[0]);
            // 如果该商品还有其他套装
            if (count($item_id_array) > 1) {
                // 过滤掉其他套装中为多品套装的
                $items = $this->itemModel->getBatchItemByIds($item_id_array);
                foreach ($items as $item) {
                    if ($item['is_spu'] == 1 && $item['spu_type'] == 1) {
                        $item_ids[] = $item['id'];
                    }
                }
            }
            // 将套装的商品id和所有套装id拼在一起，实现单品和套装互通
            array_push($item_ids, $spu_item_ids[0]);
        }
        $item_ids[] = $item_id;
        return $item_ids;
    }
    
    /**
     * 根据商品id批量获取商品
     * @param int $itemIds
     */
    public function getItemList($itemIds)
    {
        $itemInList = $this->itemModel->getBatchItemByIds($itemIds);
        return $this->succ($itemInList);
    }
    
    /**
     * 批量获取商品信息
     */
    public function getBatchItemBrandByIds($itemsIds)
    {
        $data = $this->itemModel->getBatchItemBrandByIds($itemsIds);
        return $this->succ($data);
    }
    
    /**
     * 批量查询用户是否为商家
     */
    public function getBatchUserSupplierMapping($user_ids)
    {
        if (empty($user_ids)) {
            return $this->succ(array());
        }
        $result = $this->itemModel->getBatchUserSupplierMapping($user_ids);
        return $this->succ($result);
    }
    
    /**
     * 通过商家ID查找用户id
     */
    public function getMappingBySupplierId($supplier_id)
    {
        $result = $this->itemModel->getMappingBySupplierId($supplier_id);
        return $this->succ($result);
    }
    
    /**
     * 添加商家和蜜芽圈用户的关联关系
     */
    public function addUserSupplierMapping($supplier_id, $user_id)
    {
        $result = $this->itemModel->addUserSupplierMapping($supplier_id, $user_id);
        return $this->succ($result);
    }
}
