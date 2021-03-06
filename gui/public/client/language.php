<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 *
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 *
 * The Original Code is "VHCS - Virtual Hosting Control System".
 *
 * The Initial Developer of the Original Code is moleSoftware GmbH.
 * Portions created by Initial Developer are Copyright (C) 2001-2006
 * by moleSoftware GmbH. All Rights Reserved.
 *
 * Portions created by the ispCP Team are Copyright (C) 2006-2010 by
 * isp Control Panel. All Rights Reserved.
 *
 * Portions created by the i-MSCP Team are Copyright (C) 2010-2017 by
 * i-MSCP - internet Multi Server Control Panel. All Rights Reserved.
 */

/***********************************************************************************************************************
 * Main
 */

require_once 'imscp-lib.php';

iMSCP_Events_Aggregator::getInstance()->dispatch(iMSCP_Events::onClientScriptStart);
check_login('user');

$tpl = new iMSCP_pTemplate();
$tpl->define_dynamic('layout', 'shared/layouts/ui.tpl');
$tpl->define_dynamic(array(
    'page'                => 'client/language.tpl',
    'page_message'        => 'layout',
    'languages_available' => 'page',
    'def_language'        => 'languages_available'
));


if (isset($_SESSION['logged_from']) && isset($_SESSION['logged_from_id'])) {
    list($customerCurrentLanguage) = get_user_gui_props($_SESSION['user_id']);
} else {
    $customerCurrentLanguage = $_SESSION['user_def_lang'];
}

if (!empty($_POST)) {
    $customerId = $_SESSION['user_id'];
    $customerNewLanguage = clean_input($_POST['def_language']);

    if ($customerCurrentLanguage != $customerNewLanguage) {
        exec_query('UPDATE user_gui_props SET lang = ? WHERE user_id = ?', array(
            $customerNewLanguage, $_SESSION['user_id']
        ));

        if (!isset($_SESSION['logged_from_id'])) {
            unset($_SESSION['user_def_lang']);
            $_SESSION['user_def_lang'] = $customerNewLanguage;
        }

        set_page_message(tr('Language successfully updated.'), 'success');
    } else {
        set_page_message(tr("Nothing has been changed."), 'info');
    }

    redirectTo('index.php');
}

$tpl->assign(array(
    'TR_PAGE_TITLE'      => tr('Client / Profile / Language'),
    'TR_GENERAL_INFO'    => tr('General information'),
    'TR_LANGUAGE'        => tr('Language'),
    'TR_CHOOSE_LANGUAGE' => tr('Choose your language'),
    'TR_UPDATE'          => tr('Update')
));

generateNavigation($tpl);
gen_def_language($tpl, $customerCurrentLanguage);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');
iMSCP_Events_Aggregator::getInstance()->dispatch(iMSCP_Events::onClientScriptEnd, array('templateEngine' => $tpl));
$tpl->prnt();
