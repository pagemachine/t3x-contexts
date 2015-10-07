<?php

use TYPO3\CMS\Backend\Utility\BackendUtility;
/***************************************************************
*  Copyright notice
*
*  (c) 2013 Netresearch GmbH & Co. KG <typo3-2013@netresearch.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * USER functions to render the defaults and record settings fields
 *
 * @author Christian Opitz <christian.opitz@netresearch.de>
 */
class Tx_Contexts_Service_Tca
{
    /**
     * Render the context settings field for a certain table
     *
     * @param array          $params Array of record information
     *                               - table - table name
     *                               - row   - array with database row data
     * @param t3lib_TCEforms $fobj
     * @return string
     */
    public function renderRecordSettingsField($params, $fobj)
    {
        global $TCA;
        $table = $params['table'];

        $fobj->addStyleSheet(
            'tx_contexts_bestyles',
            t3lib_extMgm::extRelPath('contexts') . 'Resources/Public/StyleSheet/be.css'
        );

        $contexts = new Tx_Contexts_Context_Container();
        $contexts->initAll();

        $namePre = str_replace('[' . $params['field'] . '_', '[' . $params['field'] . '][', $params['itemFormElName']);

        $settings = $params['fieldConf']['config']['settings'];
        $content = '';

        //Check for the current workspace. If it's not LIVE, throw info message and disable context editing further down
        $currentWorkspace = $GLOBALS['BE_USER']->workspace;
        if ($currentWorkspace != 0) {
            $message = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                 'Contexts are read-only in this workspace. Switch to LIVE version to change them.',
                 '',
                 \TYPO3\CMS\Core\Messaging\FlashMessage::INFO,
                 FALSE
            );
            $content .= $message->render();
        }


        $content .= '<table class="tx_contexts_table_settings typo3-dblist" style="width: auto; min-width:50%">'
            . '<tbody>'
            . '<tr class="t3-row-header">'
            . '<td></td>'
            . '<td class="tx_contexts_context">' .
            $fobj->sL('LLL:' . Tx_Contexts_Api_Configuration::LANG_FILE . ':tx_contexts_contexts') .
            '</td>';
        foreach ($settings as $settingName => $config) {
            $content .= '<td class="tx_contexts_setting">' . $fobj->sL($config['label']) . '</td>';
        }
        $content .= '</tr>';

        $uid = (int) $params['row']['uid'];
        $row = $params['row'];

        //Is this a Workspace Overlay? If so, load original record for plain info view
        if ($currentWorkspace != 0 && $params['row']['t3ver_oid'] != 0) {
            $uid = (int) $params['row']['t3ver_oid'];
            $row = BackendUtility::getLiveVersionOfRecord($table, $params['row']['uid']);
        }
        $visibleContexts = 0;
        foreach ($contexts as $context) {
            if ($context->getDisabled() || $context->getHideInBackend()) {
                continue;
            }

            /* @var $context Tx_Contexts_Context_Abstract */
            ++$visibleContexts;
            $contSettings = '';
            $bHasSetting = false;
            foreach ($settings as $settingName => $config) {
                $setting = $uid ? $context->getSetting($table, $settingName, $uid, $row) : null;
                $bHasSetting = $bHasSetting || (bool) $setting;
                if ($currentWorkspace != 0) {
                    $contSettings .= '<td class="tx_contexts_setting">';
                    if ($setting && $setting->getEnabled()) {
                        $contSettings .= '<span class="context-active">Yes</span>';
                    } else if ($setting && !$setting->getEnabled()) {
                        $contSettings .= '<span class="context-active">No</span>';
                    } else {
                        $contSettings .= 'n/a';
                    }
                    $contSettings .= '</td>';
                } else {
                    $contSettings .= '<td class="tx_contexts_setting">'
                        . '<select name="' . $namePre . '[' . $context->getUid() . '][' . $settingName . ']">'
                        . '<option value="">n/a</option>'
                        . '<option value="1"' . ($setting && $setting->getEnabled() ? ' selected="selected"' : '') . '>Yes</option>'
                        . '<option value="0"' . ($setting && !$setting->getEnabled() ? ' selected="selected"' : '') . '>No</option>'
                        . '</select></td>';
                }

            }

            list($icon, $title) = $this->getRecordPreview($context, $uid);
            $content .= '<tr class="db_list_normal">'
                . '<td class="tx_contexts_context col-icon"">'
                . $icon . '</td>'
                . '<td class="tx_contexts_context">'
                . '<span class="context-' . ($bHasSetting ? 'active' : 'inactive') . '">'
                . $title
                . '</span>'
                . '</td>'
                . $contSettings
                . '</tr>';
        }
        if ($visibleContexts == 0) {
            $content .= '<tr>'
                . '<td colspan="4" style="text-align: center">'
                . $fobj->sL('LLL:' . Tx_Contexts_Api_Configuration::LANG_FILE . ':no_contexts')
                . '</td>'
                . '</tr>';
        }

        $content .= '</tbody></table>';

        return $content;
    }

    /**
     * Get the standard record view for context records
     *
     * @param Tx_Contexts_Context_Abstract $context
     * @param int                          $thisUid
     *
     * @return array First value is click icon, second is title
     */
    protected function getRecordPreview($context, $thisUid)
    {
        $row = array(
            'uid'   => $context->getUid(),
            'pid'   => 0,
            'type'  => $context->getType(),
            'alias' => $context->getAlias()
        );

        return array(
            $this->getClickMenu(
                t3lib_iconWorks::getSpriteIconForRecord(
                    'tx_contexts_contexts',
                    $row,
                    array(
                        'style' => 'vertical-align:top',
                        'title' => htmlspecialchars(
                            $context->getTitle() .
                            ' [UID: ' . $row['uid'] . ']')
                    )
                ),
                'tx_contexts_contexts',
                $row['uid']
            ),
            htmlspecialchars($context->getTitle()) .
            ' <span class="typo3-dimmed"><em>[' . $row['uid'] . ']</em></span>'
        );
    }

    /**
     * Wraps the icon of a relation item (database record or file) in a link
     * opening the context menu for the item.
     *
     * Copied from class.t3lib_befunc.php
     *
     * @param string  $str   The icon HTML to wrap
     * @param string  $table Table name (eg. "pages" or "tt_content") OR the
     *                       absolute path to the file
     * @param integer $uid   The uid of the record OR if file, just blank value.
     * @return string HTML
     */
    protected function getClickMenu($str, $table, $uid = '')
    {
        $onClick = htmlspecialchars($GLOBALS['SOBE']->doc->wrapClickMenuOnIcon(
            $str, $table, $uid, 1, '', '+info,edit,view,new', TRUE
        ));
        return
        '<a href="#" onclick="' . $onClick . '" onrightclick="' . $onClick . '">' . $str . '</a>';
    }


    /**
     * Render a checkbox for the default settings of records in
     * this table
     *
     * @param array $params
     * @param t3lib_TCEforms $fobj
     * @return string
     */
    public function renderDefaultSettingsField($params, $fobj)
    {
        global $TCA;
        $table = $params['fieldConf']['config']['table'];
        t3lib_div::loadTCA($table);

        $content = '';

        $namePre = str_replace('[default_settings_', '[default_settings][', $params['itemFormElName']);

        /* @var $context Tx_Contexts_Context_Abstract */
        $uid = (int) $params['row']['uid'];
        $context = $uid
            ? Tx_Contexts_Context_Container::get()->initAll()->find($uid)
            : null;

        foreach ($params['fieldConf']['config']['settings'] as $setting => $config) {
            $id = $params['itemFormElID'] . '-' . $setting;
            $name = $namePre . '[' . $setting . ']';
            $content .= '<input type="hidden" name="' . $name . '" value="0"/>';
            $content .= '<input class="checkbox" type="checkbox" name="' . $name . '" ';
            if (
                !$context ||
                !$context->hasSetting($table, $setting, 0) ||
                $context->getSetting($table, $setting, 0)->getEnabled()
            ) {
                $content .= 'checked="checked" ';
            }
            $content .= 'value="1" id="' . $id . '" /> ';
            $content .= '<label for="' . $id . '">';
            $content .= $fobj->sL($config['label']);
            $content .= '</label><br/>';
        }

        return $content;
    }

}
?>