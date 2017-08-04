<?php
/**
 * 消息类型，目前用到的类型 2017/5/23
 * ------社交------
 *
 * 'img_comment'  图片评论
 * 'img_like'  图片赞
 * 'follow'  关注
 * 'add_fine' 帖子加精 v5.4
 * 'group_coupon'  优惠券
 * 'group_custom'  自定义 （跳转为自定义链接）
 *
 * ------特卖------
 *
 * 'order'  订单
 * 'coupon'
 * 'custom'  自定义 （跳转为自定义链接）
 */

//特卖
$outlets = ['order', 'coupon', 'custom'];

//社交
$group = ['img_comment', 'img_like', 'follow', 'add_fine', 'group_coupon', 'group_custom'];

//蜜芽圈首页社交
$group_index = ['img_comment', 'img_like', 'follow'];

//所有类型
$all_type = ['order', 'coupon', 'custom', 'img_comment', 'img_like', 'follow', 'add_fine', 'group_coupon', 'group_custom'];


/*==============================5.7消息==============================*/

/**
 * 消息目前分三级，分类列表可以展示一二级的分类
 */

/**
 * 5大类
 * trade-交易物流；
 * plus-会员plus；
 * group-蜜芽圈（活动，动态）；
 * activity-蜜芽活动；
 * property-我的资产；
 */
$newsType = ['trade', 'plus', 'group', 'activity', 'coupon'];

/**
 * 消息类
 * 最低层级的上一级，用于展示分类
 * 注意：名称不要重复！！！！！
 * group_custom
 * coupon
 * img_comment
 * add_fine
 * img_like
 * custom
 * follow
 * order
 */
$layer = [
    "trade" => [
        "order",//旧类型，只有文字content
        "order_unpay",
        "order_cancel",
        "order_send_out",
        "order_delivery",
        "return_audit_pass",
        "return_audit_refuse",
        "return_overdue",
        "refund_success",
        "refund_fail"
    ],
    "plus" => [
        "plus_active" => [
            "plus_active"
        ],
        "plus_interact" => [
            "plus_new_members",
            "plus_new_fans",
            "plus_get_commission"
        ]
    ],
    "group" => [
        "group_active" => [
            "group_custom"
        ],
        "group_interact" => [
            "img_comment",
            "add_fine",
            "img_like",
            "follow",
            "new_subject"
        ]
    ],
    "activity" => [
        "custom"
    ],
    "property" => [
        "coupon",//旧类型
        "coupon_receive",
        "coupon_overdue",
        "redbag_receive",
        "redbag_overdue"
    ]
];

//会员Plus站内信：news_type类型
$plus_news_type = [
    "plus_new_members",
    "plus_new_fans",
    "plus_get_commission"
];

//交易相关站内信：order_status订单状态
$trade_order_status = [
    "order_unpay",
    "order_cancel",
    "order_send_out",
    "order_delivery",
    "return_audit_pass",
    "return_audit_refuse",
    "return_overdue",
    "refund_success",
    "refund_fail"
];

//用户资产站内信：news_type类型
$property_news_type = [
    "coupon_receive",
    "coupon_overdue",
    "redbag_receive",
    "redbag_overdue"
];

//消息类型和模板的对应关系
$template_news_type = [
    //站内信子分类模板，展示分类用的
    "news_sub_category_template" => [
        "trade",
        "plus_active",
        "plus_interact",
        "group_active",
        "group_interact",
        "activity",
        "property"
    ],
    //以下是消息列表用的，最低级分类模板
    "news_text_pic_template" => [


    ],
    "news_pic_template" => [

    ],
    "news_banner_template" => [

    ],
    "news_miagroup_template" => [

    ],
];

$new_index_title = [
    "trade" => "交易物流",
    "plus_active" => "会员Plus",
    "plus_interact" => "会员Plus",
    "group_active" => "蜜芽圈",
    "group_interact" => "蜜芽圈",
    "activity" => "蜜芽活动",
    "property" => "我的资产"
];

$new_index_img = [
    "trade" => "",
    "plus_active" => "",
    "plus_interact" => "",
    "group_active" => "",
    "group_interact" => "",
    "activity" => "",
    "property" => ""
];

$new_index_url = [
    "trade" => "",
    "plus_active" => "",
    "plus_interact" => "",
    "group_active" => "",
    "group_interact" => "",
    "activity" => "",
    "property" => ""
];

