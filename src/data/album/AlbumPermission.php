<?php
namespace mia\miagroup\Data\Album;

use \DB_Query;

class AlbumPermission extends \DB_Query {

    protected $dbResource = 'miagroup';
    protected $tableName = 'group_subject_album_permission';
    protected $mapping = array();

    /**
     * 查用户编辑文章权限
     * @params array() $user_id 用户ID
     * @return array() id
     */
    public function getAlbumPermissionByUserId($user_id) {
        $where = array();
        $where[] = array(':eq', 'status', '1');
        $where[] = array(':eq', 'user_id', $user_id);
        
        $data = $this->getRow($where, 'id');
        return $data;
    }

    
}