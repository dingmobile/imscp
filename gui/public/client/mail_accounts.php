<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 * Copyright (C) 2010-2017 by i-MSCP Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// TODO (nuxwin): DataTables server-side processing

/***********************************************************************************************************************
 * Functions
 */

/**
 * Count the number of email addresses created by default
 *
 * @param int $mainDmnId Main domain id
 * @return int Number of default mails adresses
 */
function _client_countDefaultMails($mainDmnId)
{
    $stmt = exec_query(
        '
          SELECT
            COUNT(mail_id)
          FROM
            mail_users
          WHERE
            domain_id = ?
          AND
            (status = ? OR status = ?)
          AND
           (mail_acc = ? OR mail_acc = ? OR mail_acc = ?)
        ',
        array($mainDmnId, 'ok', 'toadd', 'abuse', 'postmaster', 'webmaster')
    );

    return $stmt->fetchRow(PDO::FETCH_COLUMN);
}

/**
 * Generate user mail action links
 *
 * @param int $mailId Mail account unique identifier
 * @param string $mailStatus Mail account status
 * @return array
 */
function _client_generateUserMailAction($mailId, $mailStatus)
{
    if ($mailStatus == 'ok') {
        return array(tr('Delete'), "mail_delete.php?id=$mailId", tr('Edit'), "mail_edit.php?id=$mailId");
    }

    return array(tr('N/A'), '#', tr('N/A'), '#');
}

/**
 * Generate auto-resonder action links
 *
 * @param iMSCP_pTemplate $tpl pTemplate instance
 * @param int $mailId Mail uique identifier
 * @param string $mailStatus Mail status
 * @param bool $mailAutoRespond
 * @return void
 */
function _client_generateUserMailAutoRespond($tpl, $mailId, $mailStatus, $mailAutoRespond)
{
    if ($mailStatus != 'ok') {
        $tpl->assign('AUTO_RESPOND_ITEM', '');
        return;
    }

    if (!$mailAutoRespond) {
        $tpl->assign(array(
            'AUTO_RESPOND'           => tr('Enable'),
            'AUTO_RESPOND_SCRIPT'    => "mail_autoresponder_enable.php?mail_account_id=$mailId",
            'AUTO_RESPOND_EDIT_LINK' => ''
        ));
    } else {
        $tpl->assign(array(
            'AUTO_RESPOND'             => tr('Disable'),
            'AUTO_RESPOND_SCRIPT'      => "mail_autoresponder_disable.php?mail_account_id=$mailId",
            'AUTO_RESPOND_EDIT'        => tr('Edit'),
            'AUTO_RESPOND_EDIT_SCRIPT' => "mail_autoresponder_edit.php?mail_account_id=$mailId",
        ));
        $tpl->parse('AUTO_RESPOND_EDIT_LINK', 'auto_respond_edit_link');
    }

    $tpl->parse('AUTO_RESPOND_ITEM', 'auto_respond_item');
}

/**
 * Generate Mail accounts list
 *
 * @param iMSCP_pTemplate $tpl Template engine
 * @param int $mainDmnId Customer main domain unique identifier
 * @return int number of mail accounts
 */
function _client_generateMailAccountsList($tpl, $mainDmnId)
{
    $stmt = exec_query(
        "
          SELECT mail_id, CONCAT(LEFT(mail_forward, 30), IF(LENGTH(mail_forward) > 30, '...', '')) AS mail_forward,
            mail_type, status, mail_auto_respond, quota, mail_addr
          FROM mail_users
          WHERE domain_id = ?
          AND mail_type NOT LIKE '%catchall%'
          ORDER BY mail_addr ASC, mail_type DESC
        ",
        $mainDmnId
    );

    $rowCount = $stmt->rowCount();

    if (!$rowCount) {
        return 0;
    }

    $postfixConfig = new iMSCP_Config_Handler_File(
        utils_normalizePath(iMSCP_Registry::get('config')->CONF_DIR . '/postfix/postfix.data')
    );

    $syncQuotaInfo = isset($_GET['sync_quota_info']);
    $hasMailboxes = false;
    $overQuota = false;

    while ($row = $stmt->fetchRow(PDO::FETCH_ASSOC)) {
        list($mailDelete, $mailDeleteScript, $mailEdit, $mailEditScript) = _client_generateUserMailAction(
            $row['mail_id'], $row['status']
        );

        $mailAddr = $row['mail_addr'];
        $mailTypes = explode(',', $row['mail_type']);
        $mailType = '';

        foreach ($mailTypes as $type) {
            $mailType .= user_trans_mail_type($type);

            if (strpos($type, '_forward') !== false) {
                $mailType .= ': ' . str_replace(',', ', ', $row['mail_forward']);
            }

            $mailType .= '<br>';
        }

        if ($row['status'] == 'ok' && strpos($row['mail_type'], '_mail') !== false) {
            $hasMailboxes = true;
            list($user, $domain) = explode('@', $row['mail_addr']);
            $maildirsize = ($row['quota']) ? parseMaildirsize(
                utils_normalizePath($postfixConfig['MTA_VIRTUAL_MAIL_DIR'] . "/$domain/$user/maildirsize"),
                $syncQuotaInfo
            ) : false;

            if ($maildirsize !== false) {
                $quotaPercent = min(100, round(($maildirsize['BYTE_COUNT'] / max(1, $maildirsize['QUOTA_BYTES'])) * 100));

                if ($quotaPercent >= 100) {
                    $overQuota = true;
                }

                $mailQuotaInfo = sprintf(
                    ($quotaPercent >= 95) ? '<span style="color:red">%s / %s (%.0f%%)</span>' : '%s / %s (%.0f%%)',
                    bytesHuman($maildirsize['BYTE_COUNT'], NULL, 1),
                    bytesHuman($maildirsize['QUOTA_BYTES'], NULL, 1),
                    $quotaPercent
                );
            } else {
                $mailQuotaInfo = ($row['quota']) ? tr('n/a') : tr('Unlimited');
            }
        } else {
            $mailQuotaInfo = tr('n/a');
        }

        $tpl->assign(array(
            'MAIL_ADDR'          => tohtml(decode_idna($mailAddr)),
            'MAIL_TYPE'          => $mailType,
            'MAIL_QUOTA_INFO'    => $mailQuotaInfo,
            'MAIL_STATUS'        => translate_dmn_status($row['status']),
            'MAIL_DELETE'        => $mailDelete,
            'MAIL_DELETE_SCRIPT' => $mailDeleteScript,
            'MAIL_EDIT'          => $mailEdit,
            'MAIL_EDIT_SCRIPT'   => $mailEditScript,
            'DEL_ITEM'           => $row['mail_id'],
            'DISABLED_DEL_ITEM'  => ($row['status'] != 'ok') ? ' disabled' : ''
        ));

        _client_generateUserMailAutoRespond($tpl, $row['mail_id'], $row['status'], $row['mail_auto_respond']);
        $tpl->parse('MAIL_ITEM', '.mail_item');
    }

    if ($syncQuotaInfo) {
        set_page_message(tr('Mailboxes quota info were synced.'), 'success');
        redirectTo('mail_accounts.php');
    }

    if (!$hasMailboxes) {
        $tpl->assign('SYNC_QUOTA_INFO_LINK', '');
    }

    if ($overQuota) {
        set_page_message(tr('At least one of your mailboxes is over quota.'), 'static_warning');
    }

    return $rowCount;
}

/**
 * Generate page
 *
 * @param iMSCP_pTemplate $tpl Reference to the pTemplate object
 * @return void
 */
function client_generatePage($tpl)
{
    if (!customerHasFeature('mail')) {
        $tpl->assign('MAIL_FEATURE', '');
        set_page_message(tr('Mail feature is disabled.'), 'static_info');
        return;
    }

    $cfg = iMSCP_Registry::get('config');
    $dmnProps = get_domain_default_props($_SESSION['user_id']);
    $mainDmnId = $dmnProps['domain_id'];
    $dmnMailAccLimit = $dmnProps['domain_mailacc_limit'];
    $countedMails = _client_generateMailAccountsList($tpl, $mainDmnId);
    $defaultMails = _client_countDefaultMails($mainDmnId);

    if (!$cfg['COUNT_DEFAULT_EMAIL_ADDRESSES']) {
        $countedMails -= $defaultMails;
    }

    $totalMails = tr(
        'Total mails: %s / %s %s', $countedMails, translate_limit_value($dmnMailAccLimit), ($defaultMails)
        ? (($cfg['COUNT_DEFAULT_EMAIL_ADDRESSES'])
            ? '(' . tr('Incl. default mails') . ')'
            : '(' . tr('Excl. default mails') . ')')
        : ''
    );

    if ($countedMails || $defaultMails) {
        $tpl->assign('TOTAL_MAIL_ACCOUNTS', $totalMails);
        return;
    }

    $tpl->assign('MAIL_ITEMS', '');
    set_page_message(tr('Mail accounts list is empty.'), 'static_info');
}

/***********************************************************************************************************************
 * Main
 */

require_once 'imscp-lib.php';

iMSCP_Events_Aggregator::getInstance()->dispatch(iMSCP_Events::onClientScriptStart);
check_login('user');

if (!customerHasMailOrExtMailFeatures()) {
    showBadRequestErrorPage();
}

$tpl = new iMSCP_pTemplate();
$tpl->define_dynamic(array(
    'layout'                 => 'shared/layouts/ui.tpl',
    'page'                   => 'client/mail_accounts.tpl',
    'page_message'           => 'layout',
    'mail_feature'           => 'page',
    'mail_items'             => 'mail_feature',
    'mail_item'              => 'mail_items',
    'auto_respond_item'      => 'mail_item',
    'auto_respond_edit_link' => 'auto_respond_item',
    'sync_quota_info_link'   => 'mail_items'
));
$tpl->assign(array(
    'TR_PAGE_TITLE'                        => tr('Client / Email / Overview'),
    'TR_MAIL'                              => tr('Mail'),
    'TR_TYPE'                              => tr('Type'),
    'TR_QUOTA_INFO'                        => tr('Quota info'),
    'TR_STATUS'                            => tr('Status'),
    'TR_ACTIONS'                           => tr('Actions'),
    'TR_AUTORESPOND'                       => tr('Auto responder'),
    'TR_DELETE'                            => tr('Delete'),
    'TR_MESSAGE_DELETE'                    => tojs(tr('Are you sure you want to delete %s?', '%s')),
    'TR_MESSAGE_DELETE_SELECTED_ITEMS'     => tojs(tr('Are you sure you want to delete all selected mail accounts?')),
    'TR_DELETE_SELECTED_ITEMS'             => tr('Delete selected mail accounts'),
    'TR_MESSAGE_DELETE_SELECTED_ITEMS_ERR' => tojs(tr('You must select a least one mail account to delete')),
    'TR_SYNC_QUOTA_INFO'                   => tr('Sync quota info'),
    'TR_SYNC_QUOTA_TOOLTIP'                => tohtml(tr('Force synching of mailboxes quota info. Quota info are automatically synced every 5 minutes.'), 'htmlAttr')
));

iMSCP_Events_Aggregator::getInstance()->registerListener('onGetJsTranslations', function ($e) {
    /** @var $e \iMSCP_Events_Event */
    $e->getParam('translations')->core['dataTable'] = getDataTablesPluginTranslations(false);
});

client_generatePage($tpl);
generateNavigation($tpl);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');
iMSCP_Events_Aggregator::getInstance()->dispatch(iMSCP_Events::onClientScriptEnd, array('templateEngine' => $tpl));
$tpl->prnt();
