<?php
namespace mia\miagroup\Service;

use \F_Ice;
use mia\miagroup\Model\User as UserModel;
use mia\miagroup\Service\UserRelation;
use mia\miagroup\Service\Subject;
use mia\miagroup\Service\Album;
use mia\miagroup\Service\Live;
use mia\miagroup\Util\NormalUtil;
use mia\miagroup\Service\Label as labelService;

class User extends \mia\miagroup\Lib\Service {

    public $userModel = null;

    public function __construct() {
        parent::__construct();
        $this->userModel = new UserModel();
    }

    /**
     * 通过用户id批量获取用户信息
     *
     * @param array $userIds     
     * @param array $fields,
     * 包括count、relation、cell_phone等
     * @return array
     */
    public function getUserInfoByUids(array $userIds, $currentUid = 0, array $fields = array()) {
        $userArr = array();
        if (empty($userIds)) {
            return array();
        }
        $userInfos = $this->userModel->getUserInfoByIds($userIds);

        if (empty($userInfos)) {
            return array();
        }
        // 如果是登陆用户，获取登录用户和发帖子用户关注的关系
        if (intval($currentUid) > 0) {
            $userRelation = new UserRelation();
            $relationWithMe = $userRelation->getUserRelationWithMe($currentUid, $userIds)['data'];
            $relationWithHim = $userRelation->getMeRelationWithUser($currentUid, $userIds)['data'];
        }
        // 批量获取用户的关注数和粉丝数
        if (in_array('count', $fields)) {
            if (!isset($userRelation)) {
                $userRelation = new UserRelation();
            }
            $subjectService = new Subject();
            $albumService = new Album();
            $userFansCount = $userRelation->countBatchUserFanS($userIds)['data']; // 用户粉丝数
            $userAttenCount = $userRelation->countBatchUserAtten($userIds)['data']; // 用户关注数
            $userSubjectsCount = $subjectService->getBatchUserSubjectCounts($userIds)['data']; // 用户发布数
            $userArticleCount = $albumService->getArticleNum($userIds)['data'];//用户文章数
        }
        // 批量获取专家信息
        $expertInfos = $this->getBatchCategoryUserInfo($userIds,'expert')['data'];
        // 批量获取是否是供应商
        $itemService = new \mia\miagroup\Service\Item();
        $supplierInfos = $itemService->getBatchUserSupplierMapping($userIds)['data'];
        // 批量获取直播权限
        $liveService = new Live();
        $liveAuths = $liveService->checkLiveAuthByUserIds($userIds)['data'];
        // 批量获取达人网站发布权限
        $publishPermissions = $this->getBatchPermissionUserInfo($userIds, 'album')['data'];
        // 批量获取发视频权限
        $videoPermissions = $this->getBatchPermissionUserInfo($userIds, 'video')['data'];
        //批量获取推荐信息
        $recommendInfos = $this->getBatchCategoryUserInfo($userIds,'doozer')['data'];

        $labelService = new labelService();
        foreach ($userInfos as $userInfo) {
            $userInfo['is_have_live_permission'] = $liveAuths[$userInfo['id']];
            $userInfo['is_experts'] = !empty($expertInfos[$userInfo['id']]) ? 1 : 0; // 用户是否是专家
            $userInfo['is_supplier'] = $supplierInfos[$userInfo['id']]['status'] == 1 ? 1 : 0; // 用户是否是供应商
            $userInfo['is_have_permission'] = !empty($videoPermissions[$userInfo['id']]) ? 1 : 0; // 用户是否有发视频权限
            $userInfo['is_have_publish_permission'] = !empty($publishPermissions[$userInfo['id']]) ? 1 : 0; // 用户是否有web发布权限
            $userInfo['doozer_intro'] = !empty($recommendInfos[$userInfo['id']]) ? $recommendInfos[$userInfo['id']]['intro'] : '';
            if ($expertInfos[$userInfo['id']]) {
                $expertInfos[$userInfo['id']]['desc'] = !empty(trim($expertInfos[$userInfo['id']]['desc'])) ? explode('#', trim($expertInfos[$userInfo['id']]['desc'], "#")) : array();
                if (!empty(trim($expertInfos[$userInfo['id']]['label'], "#"))) {
                    $expert_label_ids = explode('#', trim($expertInfos[$userInfo['id']]['label'], "#"));
                    $expertInfos[$userInfo['id']]['label'] = array_values($labelService->getBatchLabelInfos($expert_label_ids)['data']);
                } else {
                    $expertInfos[$userInfo['id']]['label'] = [];
                }
                $userInfo['experts_info'] = $expertInfos[$userInfo['id']];
            }
            
            if (intval($currentUid) > 0) {
                if (!empty($relationWithMe) && $relationWithMe[$userInfo['id']] > 0) {
                    $userInfo['relation_with_me'] = $relationWithMe[$userInfo['id']]['relation_with_me'];
                } else {
                    $userInfo['relation_with_me'] = 0;
                }
                if (!empty($relationWithHim) && $relationWithHim[$userInfo['id']] > 0) {
                    $userInfo['relation_with_him'] = $relationWithHim[$userInfo['id']]['relation_with_him'];
                } else {
                    $userInfo['relation_with_him'] = 0;
                }
            }
            
            if (in_array('count', $fields)) {
                $userInfo['fans_count'] = intval($userFansCount[$userInfo['id']]); // 用户粉丝数
                $userInfo['focus_count'] = intval($userAttenCount[$userInfo['id']]); // 用户关注数
                $userInfo['pic_count'] = intval($userSubjectsCount[$userInfo['id']]); // 用户发布数
                $userInfo['article_count'] = intval($userArticleCount[$userInfo['id']]); // 用户文章数
            }
            if (!in_array('cell_phone', $fields)) {
                unset($userInfo['cell_phone']);
            }
            $userArr[$userInfo['id']] = $this->_optimizeUserInfo($userInfo, $currentUid)['data'];
        }
        return $this->succ($userArr);
    }
    
    // 批量获取专家信息
    public function getBatchExpertInfoByUids($userIds) {
        if (empty($userIds)) {
            return array();
        }
        
        $userModel = new UserModel();
        $data = $userModel->getBatchExpertInfoByUids($userIds);
        return $this->succ($data);
    }

    /**
     *
     * @param array $userInfo            
     * @return array
     */
    private function _optimizeUserInfo($userInfo, $currentUid = 0) {
        $userInfo['user_id'] = $userInfo['id'];
        unset($userInfo['id']);
        // unset($userInfo['id']);
        foreach ($userInfo as $key => $value) {
            if (is_null($value)) {
                $userInfo[$key] = '';
            }
        }
        if ($userInfo['is_supplier'] == 1) {
            $userInfo['icon'] = !empty($userInfo['icon']) ? $userInfo['icon'] : F_Ice::$ins->workApp->config->get('busconf.user.defaultSupplierIcon');
            $userInfo['nickname'] = !empty($userInfo['nickname']) ? $userInfo['nickname'] : '蜜芽商家';
        }
        if ($userInfo['icon'] != '' && !preg_match("/^(http|https):\/\//", $userInfo['icon'])) {
            $userInfo['icon'] = F_Ice::$ins->workApp->config->get('app')['url']['img_url'] . $userInfo['icon'];
        } else if($userInfo['icon'] == '') {
            $userInfo['icon'] = F_Ice::$ins->workApp->config->get('busconf.user.defaultIcon');
        }
        $userInfo['username'] = preg_replace('/(miya[\d]{3}|mobile_[\d]{3})([\d]{4})([\d]{4})/', "$1****$3", $userInfo['username']);
        if (!$userInfo['nickname']) {
            $userInfo['nickname'] = $userInfo['username'];
        }
        
        if (in_array($userInfo['user_status'], array(1, 2))) {
            
            $userInfo['child_age'] = NormalUtil::birth_day_change($userInfo['child_birth_day']);
            $childAgeInfo = NormalUtil::getAgeByBirthday($userInfo['child_birth_day']);
            if ($childAgeInfo) {
                $userInfo['child_age_info'] = $childAgeInfo;
            }
        } else {
            unset($userInfo['child_sex']);
        }
        $userInfo['level'] = intval($userInfo['level']);
        $userInfo['level_id'] = NormalUtil::getConfig('busconf.member.level_info')[$userInfo['level']]['level_id']; // 用户等级ID
        $userInfo['level_number'] = NormalUtil::getConfig('busconf.member.level_info')[$userInfo['level']]['level']; // 用户等级
        $userInfo['level'] = NormalUtil::getConfig('busconf.member.level_info')[$userInfo['level']]['level_name']; // 用户等级名称
        $userInfo['status'] = $userInfo['status'];
        
        return $this->succ($userInfo);
    }

    /**
     * 获取单个用户的信息
     *
     * @param unknown $userId            
     * @param unknown $field
     *            包括'push_switch', 'mibean', 'count', 'cell_phone', 'jifen'等
     * @param number $currentUid
     *            当需要获取关注关系时传入
     */
    public function getUserInfoByUserId($userId, $field = array(), $currentUid = 0) {
        if (!$userId || intval($userId) <= 0) {
            return false;
        }
        $userInfo = $this->getUserInfoByUids(array($userId), $currentUid, $field)['data'];
        $userInfo = isset($userInfo[$userId]) ? $userInfo[$userId] : array();
        
        if (in_array('push_switch', $field)) {
            $pushSwitch = $this->userModel->getPushSwitchByUserIds($userInfo['id']);
            $userInfo['push_switch'] = 0;
            if (!empty($pushSwitch)) {
                $userInfo['push_switch'] = $pushSwitch['push_switch'];
            }
        }
        
        return $this->succ($userInfo);
    }
    
    /**
     * 专家详情
     */
    public function expertsInfo($userId, $currentId){
        $result = array();
        $expertsinfo = $this->getBatchCategoryUserInfo(array($userId),'expert')['data'][$userId];
        $userInfo = $this->getUserInfoByUserId($userId,array("relation","count"),$currentId)['data'];
        $result['user_info'] = $userInfo;
        if(!empty($expertsinfo)){
            $result['desc'] = !empty(trim($expertsinfo['desc'])) ? explode('#', trim($expertsinfo['desc'],"#")) : array();
            $result['expert_field'] = array();
            if(!empty(trim($expertsinfo['label'],"#"))){
                $expert_field = explode('#', trim($expertsinfo['label'],"#"));
                $labelService = new \mia\miagroup\Service\Label();
                $expert_field_info = $labelService->getBatchLabelInfos($expert_field)['data'];
                foreach ($expert_field_info as $label) {
                    $result['expert_field'][] = $label;
                }
            }else{
                $result['expert_field'] = array();
            }
            $commentService = new \mia\miagroup\Service\Comment();
            $result['comment_nums'] = $commentService->getCommentByExpertId($userId)['data'];
        }
        return $this->succ($result);
    }

    /**
     * 头条导入用户
     */
    public function syncHeadLineUser($userinfo) {
        $username = mb_strlen($userinfo['username'], 'utf8') > 18 ? mb_substr($userinfo['username'], 0, 18) : $userinfo['username'];
        $nickname = mb_strlen($userinfo['nickname'], 'utf8') > 16 ? mb_substr($userinfo['nickname'], 0, 16) : $userinfo['nickname'];
        $avatar = $userinfo['avatar'];
        $category = $userinfo['category'];
        $checkExist = $userinfo['checkExist'];
        $desc = $userinfo['desc'];
        $preNode = \DB_Query::switchCluster(\DB_Query::MASTER);
        //如果checkExist==1，nickname重复不再生成新用户
        if ($checkExist == 1) {
            $userId = $this->userModel->getUidByNickName($nickname);
            if (intval($userId) > 0) {
                //用户归类
                $this->userModel->setHeadlineUserCategory($userId, $category);
                return $this->succ(array('uid' => $userId, 'is_exist' => 1));
            }
        }
        //校验userName是否已存在
        $userId = $this->userModel->getUidByUserName($username);
        if (intval($userId) > 0) {
            //更新用户信息
            $setData[] = array('nickname', $nickname);
            $setData[] = array('icon', $avatar);
            $this->userModel->updateUserById($userId, $setData);
            //更新专家信息
            $this->updateUserCategory($userId, 'expert', array('desc' => array($desc)));
            //用户归类
            $this->userModel->setHeadlineUserCategory($userId, $category);
            return $this->succ(array('uid' => $userId, 'is_exist' => 1));
        }
        //主表插入
        $userInfo['username'] = $username;
        $userInfo['nickname'] = $nickname;
        $userInfo['icon'] = $avatar;
        $userInfo['password'] = 'a255220a91378ba2f4aad17300ed8ab7';
        $userInfo['group_id'] = 10;
        $userInfo['relation'] = 3;
        $userInfo['create_date'] = date('Y-m-d H:i:s');
        $userId = $this->userModel->addUser($userInfo);
        
        //同步到专家表
        $expertInfo = array();
        
        $expertInfo['user_id'] = $userId;
        $expertInfo['type'] = 'expert';
        $expertInfo['status'] = 1;
        $expertInfo['create_time'] = $userInfo['create_date'];
        $expertInfo['last_modify'] = $userInfo['create_date'];
        
        $this->addCategory($expertInfo);
        
        \DB_Query::switchCluster($preNode);
        if (intval($userId) > 0) {
            //用户归类
            $this->userModel->setHeadlineUserCategory($userId, $category);
            return $this->succ(array('uid' => $userId, 'is_exist' => 0));
        } else {
            return $this->error(500);
        }
    }
    
    /**
     * 生成商家在蜜芽圈的用户
     */
    public function addSupplierUser($supplier_id, $user_info) {
        //新增蜜芽圈用户
        $new_user['username'] = 'supplier_' . $supplier_id;
        $new_user['password'] = 'a255220a91378ba2f4aad17300ed8ab7';
        $new_user['group_id'] = 0;
        $new_user['relation'] = 3;
        $new_user['create_date'] = date('Y-m-d H:i:s');
        if (!empty($user_info['icon'])) {
            $new_user['icon'] = $user_info['icon'];
            $set_data[] = array('icon', $user_info['icon']);
        }
        if (!empty($user_info['nickname'])) {
            $new_user['nickname'] = $user_info['nickname'];
            $set_data[] = array('nickname', $user_info['nickname']);
        }
        $preNode = \DB_Query::switchCluster(\DB_Query::MASTER);
        $user_id = $this->userModel->addUser($new_user);
        $user_info['id'] = $user_id;
        
        //更新用户信息
        if (!empty($set_data)) {
            $this->userModel->updateUserById($user_id, $set_data);
        }
        
        //升级为专家用户
        $expertInfo = array();
        
        $expertInfo['user_id'] = $userId;
        $expertInfo['type'] = 'expert';
        $expertInfo['status'] = 1;
        $expertInfo['create_time'] = $userInfo['create_date'];
        $expertInfo['last_modify'] = $userInfo['create_date'];
        
        $this->addCategory($expertInfo);
        
        //商家用户与蜜芽圈用户绑定
        $itemService = new \mia\miagroup\Service\Item();
        $itemService->addUserSupplierMapping($supplier_id, $user_id);
        \DB_Query::switchCluster($preNode);
        return $this->succ($user_info);
    }
    
    /**
     * 新增蜜芽圈用户
     */
    public function addMiaUser($user_info) {
        if (empty($user_info['username']) || empty($user_info['nickname'])) {
            return $this->error(500);
        }
        $insert_info = array();
        $is_exist = $this->userModel->getUidByUserName($user_info['username']);
        if ($is_exist) {
            return $this->error(40001);
        }
        $is_exist = $this->userModel->getUidByNickName($user_info['nickname']);
        if ($is_exist) {
            return $this->error(40002);
        }
        $insert_info['username'] = $user_info['username'];
        $insert_info['nickname'] = $user_info['nickname'];
        if (!empty($user_info['icon'])) {
            $insert_info['icon'] = $user_info['icon'];
        }
        if (!empty($user_info['password'])) {
            $insert_info['password'] = $user_info['password'];
        }
        if (!empty($user_info['create_date'])) {
            $insert_info['create_date'] = $user_info['create_date'];
        } else {
            $insert_info['create_date'] = date('Y-m-d H:i:s');
        }
        $user_id = $this->userModel->addUser($insert_info);
        return $this->succ($user_id);
    }

    /**
     * 查推荐用户列表
     * @params array()
     * @return array() 推荐用户列表
     */
    public function getGroupDoozerList($count = 10)
    {
        $result = array();
        $userArr = $this->userModel->getGroupUserIdList('doozer',$count);
        if(!empty($userArr)){
            $result = $userArr;
        }
        return $this->succ($result);
    }
    
    // 批量获取分类用户（专家、达人）信息
    public function getBatchCategoryUserInfo($userIds, $type) {
        if (empty($userIds)) {
            return $this->error(500);
        }
    
        $conditions = array();
        $conditions['user_id'] = $userIds;
        $data = $this->userModel->getBatchUserCategory($conditions, $type);
        return $this->succ($data);
    }
    
    // 批量获取用户权限（专栏、视频）信息
    public function getBatchPermissionUserInfo($userIds, $type) {
        if (empty($userIds)) {
            return $this->error(500);
        }
    
        $conditions = array();
        $conditions['user_id'] = $userIds;
        $data = $this->userModel->getBatchUserPermission($conditions, $type);
        return $this->succ($data);
    }
    
    /**
     * 新增用户权限
     */
    public function addPermission($userInfo) {
        if(empty($userInfo['user_id'])){
            return $this->error(500);
        }
        $permissionInfo = array();
        $extInfo = array();
        
        $permissionInfo['user_id'] = $userInfo['user_id'];
        $permissionInfo['type'] = $userInfo['type'];
        $permissionInfo['source'] = isset($userInfo['source']) ? $userInfo['source'] : '';
        $permissionInfo['status'] = 1;
        $permissionInfo['create_time'] = $userInfo['create_date'];
        
        $extInfo['reason'] = isset($userInfo['reason']) ? $userInfo['reason'] : '';
        $permissionInfo['ext_info'] = json_encode($extInfo);
        unset($userInfo);
        
        $data = $this->userModel->addPermission($permissionInfo);
        return $this->succ($data);
    }
    
    /**
     * 新增用户分类
     */
    public function addCategory($userInfo) {
        if(empty($userInfo['user_id'])){
            return $this->error(500);
        }
        $catgoryInfo = array();
        $extInfo = array();
        
        $catgoryInfo['user_id'] = $userInfo['user_id'];
        $catgoryInfo['type'] = $userInfo['type'];
        $catgoryInfo['status'] = 1;
        $catgoryInfo['create_time'] = $userInfo['create_time'];
        $catgoryInfo['operator'] = $userInfo['operator'] ? $userInfo['operator'] : 0;
        if($userInfo['type'] == 'doozer'){
            $extInfo['intro'] = isset($userInfo['intro']) ? $userInfo['intro'] : '';
        }else{
            $extInfo['desc'] = isset($userInfo['desc']) ? implode('#', $userInfo['desc']) : '';
            $extInfo['label'] = isset($userInfo['label']) ? implode('#', $userInfo['label']) : '';
            $extInfo['modify_author'] = isset($userInfo['modify_author']) ? $userInfo['modify_author'] : 0;
            $extInfo['answer_nums'] = isset($userInfo['answer_nums']) ? $userInfo['answer_nums'] : 0;
            $extInfo['last_modify'] = $userInfo['create_time'];
        }
        
        if(!empty($extInfo)){
            $catgoryInfo['ext_info'] = json_encode($extInfo);
        }
        unset($userInfo);
        
        $data = $this->userModel->addCategory($catgoryInfo);
        return $this->succ($data);
    }
    
    /**
     * 更新用户权限信息
     */
    public function updateUserPermission($userId, $type, $updata) {
        if (empty($userId) || empty($updata)){
            return $this->error(500);
        }
    
        $result = array();
        $conditions = array();
        $conditions['user_id'] = array($userId);
        $userInfo = $this->userModel->getBatchUserPermission($conditions, $type)[$userId];
        if(empty($userInfo)){
            $this->succ($result);
        }
        $setData = array();
        $extInfo = array();
    
        if (isset($updata['status'])) {
            $setData[] = array('status', $updata['status']);
        }
        if (isset($updata['operator'])) {
            $setData[] = array('operator', $updata['operator']);
        }
        $userInfo['ext_info'] = json_decode($userInfo['ext_info']);
        $extInfo['reason'] = isset($updata['reason']) ? $updata['reason'] : $userInfo['reason'];
        if(!empty($extInfo)){
            $extInfo = json_encode($extInfo);
            $setData[] = array('ext_info', $extInfo);
        }
    
        $result = $this->userModel->updateUserPermission($userId, $type, $setData);
        return $this->succ($result);
    }
    
    /**
     * 更新用户分类信息
     */
    public function updateUserCategory($userId, $type, $updata) {
        if (empty($userId) || empty($updata)){
            return $this->error(500);
        }
        $result = array();
        $conditions = array();
        $conditions['user_id'] = array($userId);
        $userInfo = $this->userModel->getBatchUserCategory($conditions, $type)[$userId];
        if(empty($userInfo)){
            $this->succ($result);
        }
        $setData = array();
        $extInfo = array();
    
        if (isset($updata['status'])) {
            $setData[] = array('status', $updata['status']);
        }
        if (isset($updata['operator'])) {
            $setData[] = array('operator', $updata['operator']);
        }
        $userInfo['ext_info'] = json_decode($userInfo['ext_info']);
        if($type=='doozer'){
            $extInfo['intro'] = isset($updata['intro']) ? $updata['intro'] : $userInfo['intro'];
        }else{
            $extInfo['desc'] = isset($updata['desc']) ? implode('#', $updata['desc']) : $userInfo['desc'];
            $extInfo['label'] = isset($updata['label']) ? implode('#', $updata['label']) : $userInfo['label'];
            $extInfo['modify_author'] = isset($updata['modify_author']) ? $updata['modify_author'] : $userInfo['modify_author'];
            $extInfo['answer_nums'] = isset($updata['answer_nums']) ? $updata['answer_nums'] : $userInfo['answer_nums'];
            $extInfo['last_modify'] = date('Y-m-d H:i:s');
        }
        
        if(!empty($extInfo)){
            $extInfo = json_encode($extInfo);
            $setData[] = array('ext_info', $extInfo);
        }
        $result = $this->userModel->updateUserCategory($userId, $type, $setData);
        return $this->succ($result);
    }
    
    
}
