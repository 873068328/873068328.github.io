<?php

/**
 * ECSHOP 留言板
 * ============================================================================
 * 版权所有 2005-2008 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: testyang $
 * $Id: message.php 15013 2008-10-23 09:31:42Z testyang $
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

if (empty($_CFG['message_board']))
{
    show_message($_LANG['message_board_close']);
}
$action  = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'default';
if ($action == 'act_add_message')
{
    include_once(ROOT_PATH . 'includes/lib_clips.php');

    /* 验证码防止灌水刷屏 */
    if ((intval($_CFG['captcha']) & CAPTCHA_MESSAGE) && gd_version() > 0)
    {
        include_once('includes/cls_captcha.php');
        $validator = new captcha();
        if (!$validator->check_word($_POST['captcha']))
        {
            show_message($_LANG['invalid_captcha']);
        }
    }
    else
    {
        /* 没有验证码时，用时间来限制机器人发帖或恶意发评论 */
        if (!isset($_SESSION['send_time']))
        {
            $_SESSION['send_time'] = 0;
        }

        $cur_time = gmtime();
        if (($cur_time - $_SESSION['send_time']) < 30) // 小于30秒禁止发评论
        {
            show_message($_LANG['cmt_spam_warning']);
        }
    }
    $user_name = '';
    if (empty($_POST['anonymous']) && !empty($_SESSION['user_name']))
    {
        $user_name = $_SESSION['user_name'];
    }
    elseif (!empty($_POST['anonymous']) && !isset($_POST['user_name']))
    {
        $user_name = $_LANG['anonymous'];
    }
    elseif (empty($_POST['user_name']))
    {
        $user_name = $_LANG['anonymous'];
    }
    else
    {
        $user_name = htmlspecialchars(trim($_POST['user_name']));
    }

    $message = array(
        'user_id'     => $user_id,
        'user_name'   => $user_name,
        'user_email'  => isset($_POST['user_email']) ? htmlspecialchars(trim($_POST['user_email']))     : '',
        'msg_type'    => isset($_POST['msg_type']) ? intval($_POST['msg_type'])     : 0,
        'msg_title'   => isset($_POST['msg_title']) ? trim($_POST['msg_title'])     : '',
        'msg_content' => isset($_POST['msg_content']) ? trim($_POST['msg_content']) : '',
        'order_id'    => 0,
        'msg_area'    => 1,
        'upload'      => array()
     );

    if (add_message($message))
    {
        if (intval($_CFG['captcha']) & CAPTCHA_MESSAGE)
        {
            unset($_SESSION[$validator->session_word]);
        }
        else
        {
            $_SESSION['send_time'] = $cur_time;
        }
        show_message($_LANG['add_message_success'], $_LANG['message_list_lnk'], 'message.php');
    }
    else
    {
        $err->show($_LANG['message_list_lnk'], 'message.php');
    }
}

if ($action == 'default')
{
    assign_template();
    $position = assign_ur_here(0, $_LANG['message_board']);
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置
    $smarty->assign('helps',      get_shop_help());       // 网店帮助

    $smarty->assign('categories', get_categories_tree()); // 分类树
    $smarty->assign('top_goods',  get_top10());           // 销售排行
    $smarty->assign('cat_list',   cat_list(0, 0, true, 2, false));
    $smarty->assign('brand_list', get_brand_list());

    $smarty->assign('enabled_captcha', (intval($_CFG['captcha']) & CAPTCHA_MESSAGE));

    /* 获取留言的数量 */
    $sql = "SELECT COUNT(*) FROM " .$ecs->table('feedback').
           " WHERE `msg_area` = '1'";
    $record_count = $db->getOne($sql);

    $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
    $pagesize = get_library_number('message_list', 'message_board');
    $pager = get_pager('message.php', array(), $record_count, $page, $pagesize);
    $msg_lists = get_msg_list($pagesize, $pager['start']);

    $smarty->assign('rand',      mt_rand());
    $smarty->assign('msg_lists', $msg_lists);
    $smarty->assign('pager', $pager);
    $smarty->display('message_board.dwt');
}

/**
 * 获取留言的详细信息
 *
 * @param   integer $num
 * @param   integer $start
 *
 * @return  array
 */
function get_msg_list($num, $start)
{
    /* 获取留言数据 */
    $msg = array();
    $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('feedback');
    $sql .= " WHERE `msg_area`='1' ORDER BY msg_time DESC";


    $res = $GLOBALS['db']->SelectLimit($sql, $num, $start);

    while ($rows = $GLOBALS['db']->fetchRow($res))
    {
        $reply = array();
        $sql   = "SELECT user_name, user_email, msg_time, msg_content".
                 " FROM " .$GLOBALS['ecs']->table('feedback') .
                 " WHERE parent_id = '" . $rows['msg_id'] . "'";
        $reply = $GLOBALS['db']->getRow($sql);

        if ($reply)
        {
            $msg[$rows['msg_id']]['re_user_name']   = $reply['user_name'];
            $msg[$rows['msg_id']]['re_user_email']  = $reply['user_email'];
            $msg[$rows['msg_id']]['re_msg_time']    = local_date($GLOBALS['_CFG']['time_format'], $reply['msg_time']);
            $msg[$rows['msg_id']]['re_msg_content'] = nl2br(htmlspecialchars($reply['msg_content']));
        }

        $msg[$rows['msg_id']]['user_name'] = htmlspecialchars($rows['user_name']);
        $msg[$rows['msg_id']]['msg_content'] = nl2br(htmlspecialchars($rows['msg_content']));
        $msg[$rows['msg_id']]['msg_time']    = local_date($GLOBALS['_CFG']['time_format'], $rows['msg_time']);
        $msg[$rows['msg_id']]['msg_type']    = $GLOBALS['_LANG']['message_type'][$rows['msg_type']];
        $msg[$rows['msg_id']]['msg_title']   = nl2br(htmlspecialchars($rows['msg_title']));
        $msg[$rows['msg_id']]['message_img'] = $rows['message_img'];
        $msg[$rows['msg_id']]['order_id'] = $rows['order_id'];
    }

    return $msg;
}
?>
