<?php

/**
 * ECSHOP 批发前台文件
 * ============================================================================
 * 版权所有 2005-2008 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * @author:     scott ye <scott.yell@gmail.com>
 * @version:    v2.x
 * ---------------------------------------------
 * $Author: testyang $
 * $Id: wholesale.php 15226 2008-11-19 01:53:50Z testyang $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/* 如果没登录，提示登录 */
if ($_SESSION['user_rank'] <= 0)
{
    show_message($_LANG['ws_user_rank'], $_LANG['ws_return_home'], 'index.php');
}

/*------------------------------------------------------ */
//-- act 操作项的初始化
/*------------------------------------------------------ */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'list';
}

/*------------------------------------------------------ */
//-- 批发活动列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    /* 查询条件：当前用户的会员等级（搜索关键字） */
    $where = " WHERE w.enabled = 1 AND CONCAT(',', w.rank_ids, ',') LIKE '" . '%,' . $_SESSION['user_rank'] . ',%' . "'";

    /* 取得批发商品总数 */
    $sql = "SELECT COUNT(*) FROM " . $ecs->table('wholesale') . " AS w " . $where;
    $count = $db->getOne($sql);

    if ($count > 0)
    {
        /* 取得每页记录数 */
        $size = isset($_CFG['page_size']) && intval($_CFG['page_size']) > 0 ? intval($_CFG['page_size']) : 10;

        /* 计算总页数 */
        $page_count = ceil($count / $size);

        /* 取得当前页 */
        $page = isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0 ? intval($_REQUEST['page']) : 1;
        $page = $page > $page_count ? $page_count : $page;

        /* 取得当前页的批发商品 */
        $wholesale_list = wholesale_list($size, $page, $where);
        $smarty->assign('wholesale_list',  $wholesale_list);

        /* 设置分页链接 */
        $url = 'wholesale.php?act=list&amp;page=';
        $pager = array(
            'search'       => array('act' => 'list'),
            'page'         => $page,
            'size'         => $size,
            'record_count' => $count,
            'page_count'   => $page_count,
            'page_first'   => $url . '1',
            'page_prev'    => $page > 1 ? $url . ($page - 1) : 'javascript:;',
            'page_next'    => $page < $page_count ? $url . ($page + 1) : 'javascript:;',
            'page_last'    => $url . $page_count,
            'array'        => array()
        );
        for ($i = 1; $i <= $page_count; $i++)
        {
            $pager['array'][$i] = $i;
        }
        $smarty->assign('pager', $pager);

        /* 批发商品购物车 */
        $smarty->assign('cart_goods', isset($_SESSION['wholesale_goods']) ? $_SESSION['wholesale_goods'] : array());
    }

    /* 模板赋值 */
    assign_template();
    $position = assign_ur_here();
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置
    $smarty->assign('categories', get_categories_tree()); // 分类树
    $smarty->assign('helps',      get_shop_help());       // 网店帮助
    $smarty->assign('top_goods',  get_top10());           // 销售排行

    assign_dynamic('wholesale');

    /* 显示模板 */
    $smarty->display('wholesale_list.dwt');
}

/*------------------------------------------------------ */
//-- 下载价格单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'price_list')
{
    $data = $_LANG['goods_name'] . "\t" . $_LANG['goods_attr'] . "\t" . $_LANG['number'] . "\t" . $_LANG['ws_price'] . "\t\n";
    $sql = "SELECT * FROM " . $ecs->table('wholesale') .
            "WHERE enabled = 1 AND CONCAT(',', rank_ids, ',') LIKE '" . '%,' . $_SESSION['user_rank'] . ',%' . "'";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res))
    {
        $price_list = unserialize($row['prices']);
        foreach ($price_list as $attr_price)
        {
            if ($attr_price['attr'])
            {
                $sql = "SELECT attr_value FROM " . $ecs->table('goods_attr') .
                        " WHERE goods_attr_id " . db_create_in($attr_price['attr']);
                $goods_attr = join(',', $db->getCol($sql));
            }
            else
            {
                $goods_attr = '';
            }
            foreach ($attr_price['qp_list'] as $qp)
            {
                $data .= $row['goods_name'] . "\t" . $goods_attr . "\t" . $qp['quantity'] . "\t" . $qp['price'] . "\t\n";
            }
        }
    }

    header("Content-type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=price_list.xls");
    if (EC_CHARSET == 'utf-8')
    {
        echo ecs_iconv('UTF8', 'GB2312', $data);
    }
    else
    {
        echo $data;
    }
}

/*------------------------------------------------------ */
//-- 加入购物车
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add_to_cart')
{
    /* 取得参数 */
    $act_id = intval($_POST['act_id']);
    $goods_number = intval($_POST['goods_number'][$act_id]);
    $goods_attr = isset($_POST['attr'][$act_id]) ? $_POST['attr'][$act_id] : array();
    ksort($goods_attr);

    /* 检查数量 */
    if ($goods_number <= 0)
    {
        show_message($_LANG['ws_invalid_goods_number']);
    }
    $wholesale = wholesale_info($act_id);

    /* 检查session中该商品，该属性是否存在 */
    if (isset($_SESSION['wholesale_goods']))
    {
        foreach ($_SESSION['wholesale_goods'] as $goods)
        {
            if ($goods['goods_id'] == $wholesale['goods_id'] &&
                $goods['goods_attr_id'] == $goods_attr)
            {
                show_message($_LANG['ws_goods_attr_exists']);
            }
        }
    }

    /* 检查批发价格单中该属性是否存在 */
    $attr_matching = false;
    foreach ($wholesale['price_list'] as $attr_price)
    {
        if (empty($attr_price['attr']) || is_attr_matching($goods_attr, $attr_price['attr']))
        {
            $attr_matching = true;
            $qp_list = $attr_price['qp_list'];
            break;
        }
    }
    if (!$attr_matching)
    {
        show_message($_LANG['ws_attr_not_matching']);
    }

    /* 检查数量是否达到最低要求 */
    if ($goods_number < $qp_list[0]['quantity'])
    {
        show_message($_LANG['ws_goods_number_not_enough']);
    }
    else
    {
        $goods_price = 0;
        foreach ($qp_list as $qp)
        {
            if ($goods_number >= $qp['quantity'])
            {
                $goods_price = $qp['price'];
            }
            else
            {
                break;
            }
        }
    }

    /* 写入session */
    $sql = "SELECT attr_value FROM " . $ecs->table('goods_attr') . " WHERE goods_attr_id " . db_create_in($goods_attr);
    $goods_attr_name = join(',', $db->getCol($sql));
    $_SESSION['wholesale_goods'][] = array(
        'goods_id'      => $wholesale['goods_id'],
        'goods_name'    => $wholesale['goods_name'],
        'goods_attr_id' => $goods_attr,
        'goods_attr'    => $goods_attr_name,
        'goods_number'  => $goods_number,
        'goods_price'   => $goods_price,
        'subtotal'      => $goods_number * $goods_price,
        'formated_goods_price'  => price_format($goods_price, false),
        'formated_subtotal'     => price_format($goods_number * $goods_price, false),
        'goods_url'     => build_uri('goods', array('gid' => $wholesale['goods_id'])),
    );

    /* 刷新页面 */
    ecs_header("Location: ./wholesale.php\n");
    exit;
}

/*------------------------------------------------------ */
//-- 从购物车删除
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'drop_goods')
{
    $key = intval($_REQUEST['key']);
    if (isset($_SESSION['wholesale_goods'][$key]))
    {
        unset($_SESSION['wholesale_goods'][$key]);
    }

    /* 刷新页面 */
    ecs_header("Location: ./wholesale.php\n");
    exit;
}

/*------------------------------------------------------ */
//-- 提交订单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'submit_order')
{
    include_once(ROOT_PATH . 'includes/lib_order.php');

    /* 检查购物车中是否有商品 */
    if (count($_SESSION['wholesale_goods']) == 0)
    {
        show_message($_LANG['no_goods_in_cart']);
    }

    /* 检查备注信息 */
    if (empty($_POST['remark']))
    {
        show_message($_LANG['ws_remark']);
    }

    /* 计算商品总额 */
    $goods_amount = 0;
    foreach ($_SESSION['wholesale_goods'] as $goods)
    {
        $goods_amount += $goods['subtotal'];
    }

    $order = array(
        'postscript'      => htmlspecialchars($_POST['remark']),
        'user_id'         => $_SESSION['user_id'],
        'add_time'        => gmtime(),
        'order_status'    => OS_UNCONFIRMED,
        'shipping_status' => SS_UNSHIPPED,
        'pay_status'      => PS_UNPAYED,
        'goods_amount'    => $goods_amount,
        'order_amount'    => $goods_amount,
    );

    /* 插入订单表 */
    $error_no = 0;
    do
    {
        $order['order_sn'] = get_order_sn(); //获取新订单号
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $order, 'INSERT');

        $error_no = $GLOBALS['db']->errno();

        if ($error_no > 0 && $error_no != 1062)
        {
            die($GLOBALS['db']->errorMsg());
        }
    }
    while ($error_no == 1062); //如果是订单号重复则重新提交数据

    $new_order_id = $db->insert_id();
    $order['order_id'] = $new_order_id;

    /* 插入订单商品 */
    foreach ($_SESSION['wholesale_goods'] as $goods)
    {
        $sql = "INSERT INTO " . $ecs->table('order_goods') . "( " .
                    "order_id, goods_id, goods_name, goods_sn, goods_number, market_price, ".
                    "goods_price, goods_attr, is_real, extension_code, parent_id, is_gift) ".
                " SELECT '$new_order_id', goods_id, goods_name, goods_sn, '$goods[goods_number]', market_price, ".
                    "'$goods[goods_price]', '$goods[goods_attr]', is_real, extension_code, 0, 0 ".
                " FROM " .$ecs->table('goods') .
                " WHERE goods_id = '$goods[goods_id]'";
        $db->query($sql);
    }

    /* 给商家发邮件 */
    if ($_CFG['service_email'] != '')
    {
        $tpl = get_mail_template('remind_of_new_order');
        $smarty->assign('order', $order);
        $smarty->assign('shop_name', $_CFG['shop_name']);
        $smarty->assign('send_date', date($_CFG['time_format']));
        $content = $smarty->fetch('str:' . $tpl['template_content']);
        send_mail($_CFG['shop_name'], $_CFG['service_email'], $tpl['template_subject'], $content, $tpl['is_html']);
    }

    /* 如果需要，发短信 */
    if ($_CFG['sms_order_placed'] == '1' && $_CFG['sms_shop_mobile'] != '')
    {
        include_once('includes/cls_sms.php');
        $sms = new sms();
        $msg = $_LANG['order_placed_sms'];
        $sms->send($_CFG['sms_shop_mobile'], sprintf($msg, $order['consignee'], $order['tel']), 0);
    }

    /* 清空购物车 */
    unset($_SESSION['wholesale_goods']);

    /* 提示 */
    show_message(sprintf($_LANG['ws_order_submitted'], $order['order_sn']), $_LANG['ws_return_home'], 'index.php');
}

/**
 * 取得某页的批发商品
 * @param   int     $size   每页记录数
 * @param   int     $page   当前页
 * @param   string  $where  查询条件
 * @return  array
 */
function wholesale_list($size, $page, $where)
{
    $list = array();
    $sql = "SELECT w.*, g.goods_thumb, g.goods_name as goods_name " .
            "FROM " . $GLOBALS['ecs']->table('wholesale') . " AS w, " .
                      $GLOBALS['ecs']->table('goods') . " AS g " . $where .
            " AND w.goods_id = g.goods_id ";
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        if (empty($row['goods_thumb']))
        {
            $row['goods_thumb'] = $GLOBALS['_CFG']['no_picture'];
        }
        $row['goods_url'] = build_uri('goods', array('gid'=>$row['goods_id']));

        $properties = get_goods_properties($row['goods_id']);
        $row['goods_attr'] = $properties['pro'];

        $price_ladder = get_price_ladder($row['goods_id']);
        $row['price_ladder'] = $price_ladder;

        $list[] = $row;
    }

    return $list;
}

function get_price_ladder($goods_id)
{
    $sql = "SELECT prices FROM " . $GLOBALS['ecs']->table('wholesale') .
            "WHERE goods_id = " . $goods_id;
    $row = $GLOBALS['db']->getRow($sql);

    $arr = array();
    foreach(unserialize($row['prices']) as $key => $val)
    {
        foreach($val['qp_list'] as $index => $qp)
        {
            $arr[$qp['quantity']] = price_format($qp['price']);
        }
    }

    return $arr;
}

/**
 * 商品属性是否匹配
 * @param   array   $goods_attr     用户选择的商品属性
 * @param   array   $reference      参照的商品属性
 * @return  bool
 */
function is_attr_matching($goods_attr, $reference)
{
    foreach ($goods_attr as $attr_id => $goods_attr_id)
    {
        if (isset($reference[$attr_id]) && $reference[$attr_id] != 0 && $reference[$attr_id] != $goods_attr_id)
        {
            return false;
        }
    }

    return true;
}
?>
