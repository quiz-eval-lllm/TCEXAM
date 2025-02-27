<?php

//============================================================+
// File name   : tce_user_change_password.php
// Begin       : 2010-09-17
// Last Update : 2023-11-30
//
// Description : Form to change user password
//
// Author: Nicola Asuni
//
// (c) Copyright:
//               Nicola Asuni
//               Tecnick.com LTD
//               www.tecnick.com
//               info@tecnick.com
//
// License:
//    Copyright (C) 2004-2024 Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * @file
 * Form to change user password
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2010-09-17
 */



require_once('../config/tce_config.php');

$pagelevel = K_AUTH_USER_CHANGE_PASSWORD;
$thispage_title = $l['t_user_change_password'];
require_once('../../shared/code/tce_authorization.php');
require_once('../../shared/config/tce_user_registration.php');
require_once('../../shared/code/tce_functions_form.php');
require_once('../code/tce_page_header.php');

$user_id = (int) $_SESSION['session_user_id'];

// comma separated list of required fields
$_REQUEST['ff_required'] = 'currentpassword,newpassword,newpassword_repeat';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_current_password'] . ',' . $l['w_new_password'] . ',' . $l['w_new_password'], ENT_COMPAT, $l['a_meta_charset']);

// process submitted data
switch ($menu_mode) {
    case 'update': { // Update user
            if ($formstatus = F_check_form_fields()) {
                // check password
                if (empty($newpassword) || empty($newpassword_repeat) || $newpassword != $newpassword_repeat) {
                    //print message and exit
                    F_print_error('WARNING', $l['m_different_passwords']);
                    $formstatus = false;
                    F_stripslashes_formfields();
                    break;
                }

                $sql = 'SELECT user_password FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id;
                if ($r = F_db_query($sql, $db)) {
                    if (! ($m = F_db_fetch_array($r)) || ! checkPassword($currentpassword, $m['user_password'])) {
                        F_print_error('WARNING', $l['m_login_wrong']);
                        $formstatus = false;
                        F_stripslashes_formfields();
                        break;
                    }
                } else {
                    F_display_db_error(false);
                    break;
                }

                $sql = 'UPDATE ' . K_TABLE_USERS . ' SET
				user_password=\'' . F_escape_sql($db, getPasswordHash($newpassword)) . '\'
				WHERE user_id=' . $user_id;
                if (! $r = F_db_query($sql, $db)) {
                    F_display_db_error(false);
                } else {
                    F_print_error('MESSAGE', $l['m_password_updated']);
                }

                // Get the user's UUID (user_spring_id) from the database
                $sql = 'SELECT user_spring_id FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id;
                if ($r = F_db_query($sql, $db)) {
                    $m = F_db_fetch_array($r);
                    $user_spring_id = $m['user_spring_id'];
                } else {
                    F_display_db_error(false);
                    break;
                }

                // Prepare API payload
                $apiPayload = [
                    "name" => "", // No change to the username
                    "password" => $newpassword, // Update password
                    "email" => "", // No change to the email
                ];

                // Make API call to update the password
                $apiUrl = "http://34.27.150.5:8080/api/v1/user/" . urlencode($user_spring_id) . "/update";
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiPayload));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                // Handle API response
                if ($httpCode == 200) {
                } else {
                    F_print_error('ERROR', 'Failed to update password via API. HTTP Code: ' . $httpCode);
                    break;
                }
            }

            break;
        }

    default: {
            break;
        }
} //end of switch

echo '<div class="container">' . K_NEWLINE;

echo '<div class="gsoformbox">' . K_NEWLINE;
// echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="post" enctype="multipart/form-data" id="form_editor">' . K_NEWLINE;

// echo getFormRowTextInput('currentpassword', $l['w_current_password'], $l['h_password'], '', '', '', 255, false, false, true, '');
// echo getFormRowTextInput('newpassword', $l['w_new_password'], $l['h_password'], ' (' . $l['d_password_lenght'] . ')', '', K_USRREG_PASSWORD_RE, 255, false, false, true, '');
// echo getFormRowTextInput('newpassword_repeat', $l['w_new_password'], $l['h_password_repeat'], ' (' . $l['w_repeat'] . ')', '', '', 255, false, false, true, '');

// echo '<div class="row">' . K_NEWLINE;

// F_submit_button('update', $l['w_update'], $l['h_update']);

// echo '</div>' . K_NEWLINE;
// echo F_getCSRFTokenField() . K_NEWLINE;
// echo '</form>' . K_NEWLINE;
// echo '</div>' . K_NEWLINE;

// echo '<div class="pagehelp">' . $l['hp_user_change_password'] . '</div>' . K_NEWLINE;
echo '<div style="text-align: center; margin-bottom: 20px; padding: 10px; border: 1px solid #FF0000; background-color: #FFECEC; border-radius: 5px;">';
echo '<span style="color: red; display: block;">Unable to change password because user management is handled by SSO</span>';
echo '</div>';
echo '</div>' . K_NEWLINE;

require_once(__DIR__ . '/tce_page_footer.php');

//============================================================+
// END OF FILE
//============================================================+
