<?php

/**
 * ECSHOP 生成商品列表
 * ============================================================================
 * 版权所有 2005-2008 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: testyang $
 * $Id: affiliate.php 15013 2008-10-23 09:31:42Z testyang $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

//$charset = empty($_GET['charset']) ? 'UTF8' : $_GET['charset'];
$display_mode = empty($_GET['display_mode']) ? 'javascript' : $_GET['display_mode'];

if ( $display_mode == 'javascript' )
{
    header('content-type: application/x-javascript; charset=' . ($charset == 'UTF8' ? 'utf-8' : $charset));
}

/*------------------------------------------------------ */
//-- 判断是否存在缓存，如果存在则调用缓存，反之读取相应内容
/*------------------------------------------------------ */
/* 缓存编号 */
$cache_id = sprintf('%X', crc32($_SERVER['QUERY_STRING']));

$goodsid = intval($_GET['gid']);
$userid = intval($_GET['u']);
$type = intval($_GET['type']);


$tpl = ROOT_PATH . DATA_DIR . '/affiliate.html';
if (!$smarty->is_cached($tpl, $cache_id))
{
    $time = gmtime();
   /* 根据参数生成查询语句 */

    $goods_url = $ecs->url() . "goods.php?u=$userid&id=";
    $goods = get_goods_info($goodsid);

    $goods['shop_price'] = price_format($goods['shop_price']);

    /*if ($charset != 'UTF8')
    {
        $goods['goods_name']  = ecs_iconv('UTF8', $charset, htmlentities($goods['goods_name'], ENT_QUOTES, 'UTF-8'));
        $goods['shop_price'] = ecs_iconv('UTF8', $charset, $goods['shop_price']);
    }*/

    $smarty->assign('goods', $goods);
    $smarty->assign('userid', $userid);
    $smarty->assign('type', $type);

    $smarty->assign('url', $ecs->url());
    $smarty->assign('goods_url', $goods_url);
}
$output = $smarty->fetch($tpl, $cache_id);
$output = str_replace("\r", '', $output);
$output = str_replace("\n", '', $output);

if ( $display_mode == 'javascript' )
{
    echo "document.write('$output');";
}
else if ( $display_mode == 'iframe' )
{
    echo $output;
}

?>