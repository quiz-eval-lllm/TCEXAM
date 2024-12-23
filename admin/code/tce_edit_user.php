<?php
//============================================================+
// File name   : tce_edit_user.php
// Begin       : 2002-02-08
// Last Update : 2023-11-30
//
// Description : Edit user data.
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
 * Display form to edit users.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2002-02-08
 */



require_once('../config/tce_config.php');

$pagelevel = K_AUTH_ADMIN_USERS;
require_once('../../shared/code/tce_authorization.php');
require_once('../../shared/config/tce_user_registration.php');

$thispage_title = $l['t_user_editor'];
$enable_calendar = true;
require_once('../code/tce_page_header.php');

require_once('../../shared/code/tce_functions_form.php');
require_once('../../shared/code/tce_functions_otp.php');
require_once('tce_functions_user_select.php');

// Helper function to generate UUID
require_once('../../shared/code/tce_functions_uuid.php');


// Helper function to make API request
function callUserApi($apiUrl, $userData)
{
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['success' => false, 'error' => curl_error($ch)];
    }
    curl_close($ch);

    return ['success' => true, 'response' => $response];
}

if (isset($_REQUEST['user_id'])) {
    $user_id = (int) $_REQUEST['user_id'];
    if (! F_isAuthorizedEditorForUser($user_id)) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }
}

if (isset($_REQUEST['group_id'])) {
    $group_id = (int) $_REQUEST['group_id'];
    if (! F_isAuthorizedEditorForGroup($group_id)) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }
}

if (isset($_REQUEST['user_level'])) {
    $user_level = (int) $_REQUEST['user_level'];
    if ($_SESSION['session_user_level'] < K_AUTH_ADMINISTRATOR) {
        if ($user_id == $_SESSION['session_user_id']) {
            // you cannot change your own level
            $user_level = $_SESSION['session_user_level'];
        } else {
            // you cannot create a user with a level equal or higher than yours
            $user_level = min(max(0, ($_SESSION['session_user_level'] - 1)), $user_level);
        }
    }
}

// comma separated list of required fields
$_REQUEST['ff_required'] = 'user_name';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_name'], ENT_COMPAT, $l['a_meta_charset']);

switch ($menu_mode) { // process submitted data
    case 'delete': {
            F_stripslashes_formfields(); // ask confirmation
            if ($_SESSION['session_user_level'] < K_AUTH_DELETE_USERS || $user_id == $_SESSION['session_user_id'] || $user_id == 1) {
                F_print_error('ERROR', $l['m_authorization_denied']);
                break;
            }

            // Fetch user_spring_id
            $sql = 'SELECT user_spring_id FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id;
            $result = F_db_query($sql, $db);
            if ($row = F_db_fetch_array($result)) {
                $user_spring_id = $row['user_spring_id'];
            } else {
                F_print_error('ERROR', 'User not found.');
                break;
            }

            F_print_error('WARNING', $l['m_delete_confirm']);
?>
            <div class="confirmbox">
                <form action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="post" enctype="multipart/form-data" id="form_delete">
                    <div>
                        <input type="hidden" name="user_id" id="user_id" value="<?php echo $user_id; ?>" />
                        <input type="hidden" name="user_name" id="user_name" value="<?php echo stripslashes($user_name); ?>" />
                        <?php
                        F_submit_button('forcedelete', $l['w_delete'], $l['h_delete']);
                        F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
                        echo F_getCSRFTokenField() . K_NEWLINE;
                        ?>
                    </div>
                </form>
            </div>
<?php
            break;
        }

    case 'forcedelete': {
            F_stripslashes_formfields(); // Delete specified user
            if ($_SESSION['session_user_level'] < K_AUTH_DELETE_USERS || $user_id == $_SESSION['session_user_id'] || $user_id == 1) {
                F_print_error('ERROR', $l['m_authorization_denied']);
                break;
            }

            if ($forcedelete == $l['w_delete']) { // Check if delete button has been pushed (redundant check)
                if ($user_id == 1) { // Can't delete anonymous user
                    F_print_error('WARNING', $l['m_delete_anonymous']);
                } else {
                    // Fetch user_spring_id
                    $sql = 'SELECT user_spring_id FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id;
                    $result = F_db_query($sql, $db);
                    if ($row = F_db_fetch_array($result)) {
                        $user_spring_id = $row['user_spring_id'];

                        // Call external API to delete the user
                        $apiUrl = 'http://34.27.150.5:8080/api/v1/user/' . urlencode($user_spring_id) . '/delete';
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $apiUrl);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($httpCode == 200) {
                            // Delete user from local database
                            $sql = 'DELETE FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id;
                            if (! $r = F_db_query($sql, $db)) {
                                F_display_db_error(false);
                            } else {
                                $user_id = false;
                                F_print_error('MESSAGE', '[' . stripslashes($user_name) . '] ' . $l['m_user_deleted']);
                            }
                        } else {
                            F_print_error('ERROR', 'Failed to delete user via API. HTTP Code: ' . $httpCode);
                        }
                    } else {
                        F_print_error('ERROR', 'User not found.');
                    }
                }
            }


            break;
        }

    case 'update': { // Update user
            // check if the confirmation chekbox has been selected

            if (! isset($_REQUEST['confirmupdate']) || $_REQUEST['confirmupdate'] != 1) {
                F_print_error('WARNING', $l['m_form_missing_fields'] . ': ' . $l['w_confirm'] . ' &rarr; ' . $l['w_update']);
                F_stripslashes_formfields();
                break;
            }

            // Fetch user_spring_id
            $sql = 'SELECT user_spring_id FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id;
            $result = F_db_query($sql, $db);
            if ($row = F_db_fetch_array($result)) {
                $user_spring_id = $row['user_spring_id'];
            } else {
                F_print_error('ERROR', 'User not found.');
                break;
            }

            if ($formstatus = F_check_form_fields()) {
                // check if name is unique
                if (! F_check_unique(K_TABLE_USERS, "user_name='" . F_escape_sql($db, $user_name) . "'", 'user_id', $user_id)) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;
                    F_stripslashes_formfields();
                    break;
                }

                // check if registration number is unique
                if (isset($user_regnumber) && strlen($user_regnumber) > 0 && ! F_check_unique(K_TABLE_USERS, "user_regnumber='" . F_escape_sql($db, $user_regnumber) . "'", 'user_id', $user_id)) {
                    F_print_error('WARNING', $l['m_duplicate_regnumber']);
                    $formstatus = false;
                    F_stripslashes_formfields();
                    break;
                }

                // check if ssn is unique
                if (isset($user_ssn) && strlen($user_ssn) > 0 && ! F_check_unique(K_TABLE_USERS, "user_ssn='" . F_escape_sql($db, $user_ssn) . "'", 'user_id', $user_id)) {
                    F_print_error('WARNING', $l['m_duplicate_ssn']);
                    $formstatus = false;
                    F_stripslashes_formfields();
                    break;
                }

                // check password
                // Check if new password is provided
                if (!empty($newpassword) && !empty($newpassword_repeat)) {
                    if ($newpassword === $newpassword_repeat) {
                        $user_password = getPasswordHash($newpassword); // Hash the new password
                        $user_otpkey = F_getRandomOTPkey(); // Update OTP key
                    } else {
                        // Passwords do not match; show an error and stop
                        F_print_error('WARNING', $l['m_different_passwords']);
                        $formstatus = false;
                        break;
                    }
                } else {
                    // Retain the current password
                    $sql = 'SELECT user_password, user_otpkey FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id;
                    $result = F_db_query($sql, $db);
                    if ($row = F_db_fetch_array($result)) {
                        $user_password = $row['user_password'];
                        $user_otpkey = $row['user_otpkey'];
                    } else {
                        F_print_error('ERROR', 'Failed to retrieve existing password.');
                        $formstatus = false;
                        break;
                    }
                }


                // Determine role for API
                $group_id = !empty($user_groups) ? $user_groups[0] : K_USRREG_GROUP;
                $role = ($group_id == 1) ? 0 : (($group_id != 1) ? 1 : null);

                // Prepare API payload
                $apiPayload = [
                    "email" => !empty($user_email) ? $user_email : "",
                ];

                if ($role !== null) {
                    $apiPayload["role"] = $role;
                }

                // Make API call to update the user
                $apiUrl = "http://34.27.150.5:8080/api/v1/user/" . urlencode($user_spring_id) . "/update";
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiPayload));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $_SESSION['access_token'],
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);


                if ($httpCode == 200) {


                    $sql = 'UPDATE ' . K_TABLE_USERS . ' SET
                        user_regdate=\'' . F_escape_sql($db, $user_regdate) . '\',
                        user_ip=\'' . F_escape_sql($db, $user_ip) . '\',
                        user_name=\'' . F_escape_sql($db, $user_name) . '\',
                        user_email=' . F_empty_to_null($user_email) . ',
                        user_password=\'' . F_escape_sql($db, $user_password) . '\',
                        user_regnumber=' . F_empty_to_null($user_regnumber) . ',
                        user_firstname=' . F_empty_to_null($user_firstname) . ',
                        user_lastname=' . F_empty_to_null($user_lastname) . ',
                        user_birthdate=' . F_empty_to_null($user_birthdate) . ',
                        user_birthplace=' . F_empty_to_null($user_birthplace) . ',
                        user_ssn=' . F_empty_to_null($user_ssn) . ',
                        user_level=\'' . $user_level . '\',
                        user_otpkey=' . F_empty_to_null($user_otpkey) . '
                        WHERE user_id=' . $user_id . '';
                    if (! $r = F_db_query($sql, $db)) {
                        F_display_db_error(false);
                    } else {
                        F_print_error('MESSAGE', stripslashes($user_name) . ': ' . $l['m_user_updated']);
                    }


                    // Update user's groups locally
                    // Remove old groups
                    $old_user_groups = F_get_user_groups($user_id);
                    foreach ($old_user_groups as $group_id) {
                        if (F_isAuthorizedEditorForGroup($group_id)) {
                            // Delete previous groups
                            $sql = 'DELETE FROM ' . K_TABLE_USERGROUP . ' WHERE usrgrp_user_id=' . $user_id . ' AND usrgrp_group_id=' . $group_id;
                            if (!F_db_query($sql, $db)) {
                                F_display_db_error(false);
                            }
                        }
                    }

                    // Insert new groups
                    if (!empty($user_groups)) {
                        foreach ($user_groups as $group_id) {
                            if (F_isAuthorizedEditorForGroup($group_id)) {
                                $sql = 'INSERT INTO ' . K_TABLE_USERGROUP . ' (
                                usrgrp_user_id,
                                usrgrp_group_id
                            ) VALUES (
                                \'' . $user_id . '\',
                                \'' . $group_id . '\'
                            )';
                                if (!F_db_query($sql, $db)) {
                                    F_display_db_error(false);
                                }
                            }
                        }
                    }
                } else {
                    F_print_error('ERROR', 'Failed to update user via API. HTTP Code: ' . $httpCode);
                    F_print_error('ERROR', 'Cause: ' . $response);
                }
            }

            break;
        }

    case 'add': { // Add user
            if ($formstatus = F_check_form_fields()) { // check submittef form fields
                // check if name is unique
                if (! F_check_unique(K_TABLE_USERS, "user_name='" . $user_name . "'")) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;
                    F_stripslashes_formfields();
                    break;
                }

                // check if registration number is unique
                if (isset($user_regnumber) && strlen($user_regnumber) > 0 && ! F_check_unique(K_TABLE_USERS, "user_regnumber='" . F_escape_sql($db, $user_regnumber) . "'")) {
                    F_print_error('WARNING', $l['m_duplicate_regnumber']);
                    $formstatus = false;
                    F_stripslashes_formfields();
                    break;
                }

                // check if ssn is unique
                if (isset($user_ssn) && strlen($user_ssn) > 0 && ! F_check_unique(K_TABLE_USERS, "user_ssn='" . F_escape_sql($db, $user_ssn) . "'")) {
                    F_print_error('WARNING', $l['m_duplicate_ssn']);
                    $formstatus = false;
                    F_stripslashes_formfields();
                    break;
                }

                // check password
                if (! empty($newpassword) || ! empty($newpassword_repeat)) { // update password
                    if ($newpassword == $newpassword_repeat) {
                        $user_password = getPasswordHash($newpassword);
                        // update OTP key
                        $user_otpkey = F_getRandomOTPkey();
                    } else { //print message and exit
                        F_print_error('WARNING', $l['m_different_passwords']);
                        $formstatus = false;
                        F_stripslashes_formfields();
                        break;
                    }
                } else { //print message and exit
                    F_print_error('WARNING', $l['m_empty_password']);
                    $formstatus = false;
                    F_stripslashes_formfields();
                    break;
                }

                $user_ip = getNormalizedIP($_SERVER['REMOTE_ADDR']); // get the user's IP number
                $user_regdate = date(K_TIMESTAMP_FORMAT); // get the registration date and time

                // Fetch UUID
                $uuidResult = fetchUUID();
                if ($uuidResult['success']) {
                    $user_spring_id = $uuidResult['uuid'];
                } else {
                    F_print_error('ERROR', 'Failed to generate UUID: ' . $uuidResult['error']);
                    exit;
                }

                $sql = 'INSERT INTO ' . K_TABLE_USERS . ' (
                user_spring_id,
				user_regdate,
				user_ip,
				user_name,
				user_email,
				user_password,
				user_regnumber,
				user_firstname,
				user_lastname,
				user_birthdate,
				user_birthplace,
				user_ssn,
				user_level,
				user_otpkey
				) VALUES (
                \'' . F_escape_sql($db, $user_spring_id) . '\',
				\'' . F_escape_sql($db, $user_regdate) . '\',
				\'' . F_escape_sql($db, $user_ip) . '\',
				\'' . F_escape_sql($db, $user_name) . '\',
				' . F_empty_to_null($user_email) . ',
				\'' . F_escape_sql($db, $user_password) . '\',
				' . F_empty_to_null($user_regnumber) . ',
				' . F_empty_to_null($user_firstname) . ',
				' . F_empty_to_null($user_lastname) . ',
				' . F_empty_to_null($user_birthdate) . ',
				' . F_empty_to_null($user_birthplace) . ',
				' . F_empty_to_null($user_ssn) . ',
				\'' . $user_level . '\',
				' . F_empty_to_null($user_otpkey) . '
				)';
                if (! $r = F_db_query($sql, $db)) {
                    F_display_db_error(false);
                } else {
                    $user_id = F_db_insert_id($db, K_TABLE_USERS, 'user_id');
                }

                // add user's groups
                if (! empty($user_groups)) {
                    foreach ($user_groups as $group_id) {
                        if (F_isAuthorizedEditorForGroup($group_id)) {
                            $sql = 'INSERT INTO ' . K_TABLE_USERGROUP . ' (
							usrgrp_user_id,
							usrgrp_group_id
							) VALUES (
							\'' . $user_id . '\',
							\'' . $group_id . '\'
							)';
                            if (! $r = F_db_query($sql, $db)) {
                                F_display_db_error(false);
                            }
                        }
                    }
                }
            }

            // Determine role for API
            $group_id = !empty($user_groups) ? $user_groups[0] : K_USRREG_GROUP;
            $role = ($group_id == 1) ? 0 : (($group_id != 1) ? 1 : null);

            // Prepare API data
            $userApiData = [
                'id' => $user_spring_id,
                'name' => $user_name,
                'email' => $user_email,
                'password' => $newpassword,
                'role' => $role
            ];

            // Call the API
            $apiUrl = 'http://34.27.150.5:8080/api/v1/user';
            $apiResponse = callUserApi($apiUrl, $userApiData);

            if ($apiResponse['success']) {
                F_print_error('MESSAGE', $l['m_user_registration_ok'] . ' API call successful.');
            } else {
                F_print_error('WARNING', $l['m_user_registration_ok'] . ' API call failed: ' . $apiResponse['error']);
            }

            break;
        }

    case 'clear': { // Clear form fields
            $user_regdate = '';
            $user_ip = '';
            $user_name = '';
            $user_email = '';
            $user_password = '';
            $user_regnumber = '';
            $user_firstname = '';
            $user_lastname = '';
            $user_birthdate = '';
            $user_birthplace = '';
            $user_ssn = '';
            $user_level = '';
            $user_otpkey = '';
            break;
        }

    default: {
            break;
        }
} //end of switch

// --- Initialize variables
if ($formstatus && $menu_mode != 'clear') {
    if (! isset($user_id) || empty($user_id)) {
        $user_id = 0;
        $user_regdate = '';
        $user_ip = '';
        $user_name = '';
        $user_email = '';
        $user_password = '';
        $user_regnumber = '';
        $user_firstname = '';
        $user_lastname = '';
        $user_birthdate = '';
        $user_birthplace = '';
        $user_ssn = '';
        $user_level = '';
        $user_otpkey = '';
    } else {
        $sql = 'SELECT * FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id . ' LIMIT 1';
        if ($r = F_db_query($sql, $db)) {
            if ($m = F_db_fetch_array($r)) {
                $user_id = $m['user_id'];
                $user_regdate = $m['user_regdate'];
                $user_ip = $m['user_ip'];
                $user_name = $m['user_name'];
                $user_email = $m['user_email'];
                $user_password = $m['user_password'];
                $user_regnumber = $m['user_regnumber'];
                $user_firstname = $m['user_firstname'];
                $user_lastname = $m['user_lastname'];
                $user_birthdate = substr($m['user_birthdate'], 0, 10);
                $user_birthplace = $m['user_birthplace'];
                $user_ssn = $m['user_ssn'];
                $user_level = $m['user_level'];
                $user_otpkey = $m['user_otpkey'];
            } else {
                $user_regdate = '';
                $user_ip = '';
                $user_name = '';
                $user_email = '';
                $user_password = '';
                $user_regnumber = '';
                $user_firstname = '';
                $user_lastname = '';
                $user_birthdate = '';
                $user_birthplace = '';
                $user_ssn = '';
                $user_level = '';
                $user_otpkey = '';
            }
        } else {
            F_display_db_error();
        }
    }
}

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;

// Add the explanatory text with an outline
echo '<div style="text-align: center; margin-bottom: 20px; padding: 10px; border: 1px solid #FF0000; background-color: #FFECEC; border-radius: 5px;">';
echo '<span style="color: red; font-weight: bold;">Unable to add or remove user because user management is handled by SSO</span>';
echo '</div>';

echo '<form action="' . $_SERVER['SCRIPT_NAME'] . '" method="post" enctype="multipart/form-data" id="form_usereditor">' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="user_id">' . $l['w_user'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<select name="user_id" id="user_id" size="0" onchange="document.getElementById(\'form_usereditor\').submit()">' . K_NEWLINE;
echo '<option value="0" style="background-color:#009900;color:white;"';
if ($user_id == 0) {
    echo ' selected="selected"';
}

echo '>+</option>' . K_NEWLINE;
$sql = 'SELECT user_id, user_lastname, user_firstname, user_name FROM ' . K_TABLE_USERS . ' WHERE (user_id>1)';
if ($_SESSION['session_user_level'] < K_AUTH_ADMINISTRATOR) {
    // filter for level
    $sql .= ' AND ((user_level<' . $_SESSION['session_user_level'] . ') OR (user_id=' . $_SESSION['session_user_id'] . '))';
    // filter for groups
    $sql .= ' AND user_id IN (SELECT tb.usrgrp_user_id
		FROM ' . K_TABLE_USERGROUP . ' AS ta, ' . K_TABLE_USERGROUP . ' AS tb
		WHERE ta.usrgrp_group_id=tb.usrgrp_group_id
			AND ta.usrgrp_user_id=' . (int) $_SESSION['session_user_id'] . '
			AND tb.usrgrp_user_id=user_id)';
}

$sql .= ' ORDER BY user_lastname, user_firstname, user_name';
if ($r = F_db_query($sql, $db)) {
    $countitem = 1;
    while ($m = F_db_fetch_array($r)) {
        echo '<option value="' . $m['user_id'] . '"';
        if ($m['user_id'] == $user_id) {
            echo ' selected="selected"';
        }

        echo '>' . $countitem . '. ' . htmlspecialchars($m['user_lastname'] . ' ' . $m['user_firstname'] . ' - ' . $m['user_name'] . '', ENT_NOQUOTES, $l['a_meta_charset']) . '</option>' . K_NEWLINE;
        ++$countitem;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;

// link for user selection popup
$jsaction = "selectWindow=window.open('tce_select_users_popup.php?cid=user_id', 'selectWindow', 'dependent, height=600, width=800, menubar=no, resizable=yes, scrollbars=yes, status=no, toolbar=no');return false;";
echo '<a href="#" onclick="' . $jsaction . '" class="xmlbutton" title="' . $l['w_select'] . '">...</a>';

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo getFormNoscriptSelect('selectrecord');

echo '<div class="row"><hr /></div>' . K_NEWLINE;

// Add hidden fields for username, password, and level
echo '<input type="hidden" name="user_name" value="' . htmlspecialchars($user_name, ENT_QUOTES, $l['a_meta_charset']) . '" />';
echo '<input type="hidden" name="newpassword" value="" />';
echo '<input type="hidden" name="newpassword_repeat" value="" />';
echo '<input type="hidden" name="user_level" value="' . htmlspecialchars($user_level, ENT_QUOTES, $l['a_meta_charset']) . '" />';

// Render other editable fields
echo getFormRowTextInput('user_email', $l['w_email'], $l['h_usered_email'], '', $user_email, K_EMAIL_RE_PATTERN, 255, false, false, false);
echo getFormRowFixedValue('user_regdate', $l['w_regdate'], $l['h_regdate'], '', $user_regdate);
echo getFormRowFixedValue('user_ip', $l['w_ip'], $l['h_ip'], '', $user_ip);
echo getFormRowTextInput('user_regnumber', $l['w_regcode'], $l['h_regcode'], '', $user_regnumber, '', 255, false, false, false);
echo getFormRowTextInput('user_firstname', $l['w_firstname'], $l['h_firstname'], '', $user_firstname, '', 255, false, false, false);
echo getFormRowTextInput('user_lastname', $l['w_lastname'], $l['h_lastname'], '', $user_lastname, '', 255, false, false, false);
echo getFormRowTextInput('user_birthdate', $l['w_birth_date'], $l['h_birth_date'] . ' ' . $l['w_date_format'], '', $user_birthdate, '', 10, true, false, false);
echo getFormRowTextInput('user_birthplace', $l['w_birth_place'], $l['h_birth_place'], '', $user_birthplace, '', 255, false, false, false);
echo getFormRowTextInput('user_ssn', $l['w_fiscal_code'], $l['h_fiscal_code'], '', $user_ssn, '', 255, false, false, false);

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="user_groups">' . $l['w_groups'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<select name="user_groups[]" id="user_groups" size="5" multiple="multiple">' . K_NEWLINE;
$sql = 'SELECT * FROM ' . K_TABLE_GROUPS . ' ORDER BY group_name';
if ($r = F_db_query($sql, $db)) {
    while ($m = F_db_fetch_array($r)) {
        echo '<option value="' . $m['group_id'] . '"';
        if (! F_isAuthorizedEditorForGroup($m['group_id'])) {
            echo ' style="text-decoration:line-through;"';
        }

        if (F_isUserOnGroup($user_id, $m['group_id'])) {
            echo ' selected="selected"';
            $m['group_name'] = '* ' . $m['group_name'];
        }

        echo '>' . htmlspecialchars($m['group_name'], ENT_NOQUOTES, $l['a_meta_charset']) . '</option>' . K_NEWLINE;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo getFormRowTextInput('user_otpkey', $l['w_otpkey'], $l['h_otpkey'], '', $user_otpkey, '', 255, false, false, false);

// display QR-Code for Google authenticator
if (! empty($user_otpkey)) {
    require_once('../../shared/tcpdf/tcpdf_barcodes_2d.php');
    $host = preg_replace('/[h][t][t][p][s]?[:][\/][\/]/', '', K_PATH_HOST);
    $qrcode = new TCPDF2DBarcode('otpauth://totp/' . $user_name . '@' . $host . '?secret=' . $user_otpkey, 'QRCODE,H');
    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . $l['w_otp_qrcode'] . '</span>' . K_NEWLINE;
    echo '<span class="formw" style="margin:30px 0px 30px 0px;">' . K_NEWLINE;
    echo $qrcode->getBarcodeHTML(6, 6, 'black');
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}

// Buttons for actions
echo '<div class="row">' . K_NEWLINE;
echo '<span style="background-color:#999999;">';
echo '<input type="checkbox" name="confirmupdate" id="confirmupdate" value="1" title="confirm &rarr; update" />';
F_submit_button('update', $l['w_update'], $l['h_update']);
echo '</span>';

// Disable Add and Delete buttons with messages
echo '<button type="button" disabled style="margin: 5px;">' . $l['w_add'] . '</button>';
echo '<button type="button" disabled style="margin: 5px;">' . $l['w_delete'] . '</button>';
F_submit_button('clear', $l['w_clear'], $l['h_clear']);

echo '</div>' . K_NEWLINE;
echo F_getCSRFTokenField() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_edit_user'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once('../code/tce_page_footer.php');

//============================================================+
// END OF FILE
//============================================================+