<?php

//============================================================+
// File name   : tce_authorization.php
// Begin       : 2001-09-26
// Last Update : 2023-11-30
//
// Description : Check user authorization level.
//               Grants / deny access to pages.
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
 * This script handles user's sessions.
 * Just the registered users granted with a username and a password are entitled to access the restricted areas (level > 0) of TCExam and the public area to perform the tests.
 * The user's level is a numeric value that indicates which resources (pages, modules, services) are accessible by the user.
 * To gain access to a specific resource, the user's level must be equal or greater to the one specified for the requested resource.
 * TCExam has 10 predefined user's levels:<ul>
 * <li>0 = anonymous user (unregistered).</li>
 * <li>1 = basic user (registered);</li>
 * <li>2-9 = configurable/custom levels;</li>
 * <li>10 = administrator with full access rights</li>
 * </ul>
 * @package com.tecnick.tcexam.shared
 * @brief TCExam Shared Area
 * @author Nicola Asuni
 * @since 2001-09-26
 */



require_once('../config/tce_config.php');
require_once('../../shared/code/tce_functions_authorization.php');
require_once('../../shared/code/tce_functions_session.php');
require_once('../../shared/code/tce_functions_otp.php');

$logged = false; // the user is not yet logged in

// Generating UUID 
require_once('../../shared/code/tce_functions_uuid.php');

// --- read existing user's session data from database
$PHPSESSIDSQL = F_escape_sql($db, $PHPSESSID);
$fingerprintkey = getClientFingerprint();
$sqls = 'SELECT * FROM ' . K_TABLE_SESSIONS . " WHERE cpsession_id='" . $PHPSESSIDSQL . "'";
if ($rs = F_db_query($sqls, $db)) {
    if ($ms = F_db_fetch_array($rs)) { // the user's session already exist
        // decode session data
        session_decode($ms['cpsession_data']);
        // check for possible session hijacking
        if (K_CHECK_SESSION_FINGERPRINT && (! isset($_SESSION['session_hash']) || $fingerprintkey != $_SESSION['session_hash'])) {
            // display login form
            session_regenerate_id(true);
            F_login_form();
            exit();
        }

        // update session expiration time
        $expiry = date(K_TIMESTAMP_FORMAT, (time() + K_SESSION_LIFE));
        $sqlx = 'UPDATE ' . K_TABLE_SESSIONS . " SET cpsession_expiry='" . $expiry . "' WHERE cpsession_id='" . $PHPSESSIDSQL . "'";
        if (! $rx = F_db_query($sqlx, $db)) {
            F_display_db_error();
        }
    } else { // session do not exist so, create new anonymous session
        $_SESSION['session_hash'] = $fingerprintkey;
        $_SESSION['session_user_id'] = 1;
        $_SESSION['session_user_name'] = '- [' . substr($PHPSESSID, 12, 8) . ']';
        $_SESSION['session_user_ip'] = getNormalizedIP($_SERVER['REMOTE_ADDR']);
        $_SESSION['session_user_level'] = 0;
        $_SESSION['session_user_firstname'] = '';
        $_SESSION['session_user_lastname'] = '';
        $_SESSION['session_test_login'] = '';
        // read client cookie
        $_SESSION['session_last_visit'] = isset($_COOKIE['LastVisit']) ? (int) $_COOKIE['LastVisit'] : 0;

        // set client cookie
        $cookie_now_time = time(); // note: while time() function returns a 32 bit integer, it works fine until year 2038.
        $cookie_expire_time = $cookie_now_time + K_COOKIE_EXPIRE; // set cookie expiration time
        setcookie(
            'LastVisit',
            $cookie_now_time,
            [
                'expires' => $cookie_expire_time,
                'path' => K_COOKIE_PATH,
                'domain' => K_COOKIE_DOMAIN,
                'secure' => K_COOKIE_SECURE,
                'httponly' => K_COOKIE_HTTPONLY,
                'samesite' => K_COOKIE_SAMESITE,
            ]
        );
        setcookie(
            'PHPSESSID',
            $PHPSESSID,
            [
                'expires' => $cookie_expire_time,
                'path' => K_COOKIE_PATH,
                'domain' => K_COOKIE_DOMAIN,
                'secure' => K_COOKIE_SECURE,
                'httponly' => K_COOKIE_HTTPONLY,
                'samesite' => K_COOKIE_SAMESITE,
            ]
        );
        // track when user request logout
        if (isset($_REQUEST['logout'])) {
            $_SESSION['logout'] = true;
            if (strlen(K_LOGOUT_URL) > 0) {
                $htmlredir = '<?xml version="1.0" encoding="' . $l['a_meta_charset'] . '"?' . '>' . K_NEWLINE;
                $htmlredir .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">' . K_NEWLINE;
                $htmlredir .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $l['a_meta_language'] . '" lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;
                $htmlredir .= '<head>' . K_NEWLINE;
                $htmlredir .= '<title>LOGOUT</title>' . K_NEWLINE;
                $htmlredir .= '<meta http-equiv="refresh" content="0;url=' . K_LOGOUT_URL . '" />' . K_NEWLINE;
                $htmlredir .= '</head>' . K_NEWLINE;
                $htmlredir .= '<body>' . K_NEWLINE;
                $htmlredir .= '<a href="' . K_LOGOUT_URL . '">LOGOUT...</a>' . K_NEWLINE;
                $htmlredir .= '</body>' . K_NEWLINE;
                $htmlredir .= '</html>' . K_NEWLINE;
                header('Location: ' . K_LOGOUT_URL);
                echo $htmlredir;
                exit;
            }
        }
    }
} else {
    F_display_db_error();
}

// try other login systems
// (HTTP-BASIC, CAS, SHIBBOLETH, RADIUS, LDAP)
require_once('../../shared/code/tce_altauth.php');
$altusr = F_altLogin();

// --- check if login information has been submitted
if (isset($_POST['logaction']) && $_POST['logaction'] == 'login' && isset($_POST['xuser_name']) && isset($_POST['xuser_password'])) {
    $bruteforce = false;
    if (K_BRUTE_FORCE_DELAY_RATIO > 0) {
        // check login attempt from the current client device to avoid brute force attack
        $bruteforce = true;
        // we are using another entry in the session table to keep track of the login attempts
        $sqlt = 'SELECT * FROM ' . K_TABLE_SESSIONS . " WHERE cpsession_id='" . $fingerprintkey . "' LIMIT 1";
        if ($rt = F_db_query($sqlt, $db)) {
            if ($mt = F_db_fetch_array($rt)) {
                // check the expiration time
                if (strtotime($mt['cpsession_expiry']) < time()) {
                    $bruteforce = false;
                }

                // update wait time
                $wait = (int) $mt['cpsession_data'];
                if ($wait < K_SECONDS_IN_HOUR) {
                    $wait *= K_BRUTE_FORCE_DELAY_RATIO;
                }

                $sqlup = 'UPDATE ' . K_TABLE_SESSIONS . ' SET
					cpsession_expiry=\'' . date(K_TIMESTAMP_FORMAT, (time() + $wait)) . '\',
					cpsession_data=\'' . $wait . '\'
					WHERE cpsession_id=\'' . $fingerprintkey . "'";
                if (! F_db_query($sqlup, $db)) {
                    F_display_db_error();
                }
            } else {
                // add new record
                $wait = 1; // number of seconds to wait for the second attempt
                $sqls = 'INSERT INTO ' . K_TABLE_SESSIONS . ' (
					cpsession_id,
					cpsession_expiry,
					cpsession_data
					) VALUES (
					\'' . $fingerprintkey . '\',
					\'' . date(K_TIMESTAMP_FORMAT, (time() + $wait)) . '\',
					\'' . $wait . '\'
					)';
                if (! F_db_query($sqls, $db)) {
                    F_display_db_error();
                }

                $bruteforce = false;
            }
        }
    }

    if ($bruteforce) {
        F_print_error('WARNING', $l['m_login_brute_force'] . ' ' . $wait);
    } else {
        // encode password
        $xuser_password = getPasswordHash($_POST['xuser_password']);
        // check One-Time-Password if enabled
        $otp = false;
        if (K_OTP_LOGIN) {
            $mtime = microtime(true);
            if (isset($_POST['xuser_otpcode']) && ! empty($_POST['xuser_otpcode']) && ($_POST['xuser_otpcode'] == F_getOTP($m['user_otpkey'], $mtime) || $_POST['xuser_otpcode'] == F_getOTP($m['user_otpkey'], ($mtime - 30)) || $_POST['xuser_otpcode'] == F_getOTP($m['user_otpkey'], ($mtime + 30)))) {
                // check if this OTP token has been alredy used
                $sqlt = 'SELECT cpsession_id FROM ' . K_TABLE_SESSIONS . " WHERE cpsession_id='" . $_POST['xuser_otpcode'] . "' LIMIT 1";
                if (($rt = F_db_query($sqlt, $db)) && ! F_db_fetch_array($rt)) {
                    // Store this token on the session table to mark it as invalid for 5 minute (300 seconds)
                    $sqltu = 'INSERT INTO ' . K_TABLE_SESSIONS . ' (
							cpsession_id,
							cpsession_expiry,
							cpsession_data
							) VALUES (
							\'' . $_POST['xuser_otpcode'] . '\',
							\'' . date(K_TIMESTAMP_FORMAT, (time() + 300)) . '\',
							\'300\'
							)';
                    if (! F_db_query($sqltu, $db)) {
                        F_display_db_error();
                    }

                    $otp = true;
                }
            }
        }

        if (! K_OTP_LOGIN || $otp) {

            // <=================== OLD BRUTE FORCE METHOD ===================>
            // // check if submitted login information are correct
            // $sql = 'SELECT * FROM ' . K_TABLE_USERS . " WHERE user_name='" . F_escape_sql($db, $_POST['xuser_name']) . "'";

            // if ($r = F_db_query($sql, $db)) {
            //     if (($m = F_db_fetch_array($r)) && checkPassword($_POST['xuser_password'], $m['user_password'])) {
            //         // sets some user's session data
            //         $_SESSION['session_user_id'] = $m['user_id'];
            //         $_SESSION['session_user_name'] = $m['user_name'];
            //         $_SESSION['session_user_ip'] = getNormalizedIP($_SERVER['REMOTE_ADDR']);
            //         $_SESSION['session_user_level'] = $m['user_level'];
            //         $_SESSION['session_user_firstname'] = urlencode($m['user_firstname']);
            //         $_SESSION['session_user_lastname'] = urlencode($m['user_lastname']);
            //         $_SESSION['session_test_login'] = '';
            //         // read client cookie
            //         $_SESSION['session_last_visit'] = isset($_COOKIE['LastVisit']) ? (int) $_COOKIE['LastVisit'] : 0;

            //         $logged = true;
            //         if (K_USER_GROUP_RSYNC && $altusr !== false) {
            //             // sync user groups
            //             F_syncUserGroups($_SESSION['session_user_id'], $altusr['usrgrp_group_id']);
            //         }
            //     } elseif (! F_check_unique(K_TABLE_USERS, "user_name='" . F_escape_sql($db, $_POST['xuser_name']) . "'")) {
            //         // the user name exist but the password is wrong
            //         if ($altusr !== false) {
            //             // resync the password
            //             $sqlu = 'UPDATE ' . K_TABLE_USERS . ' SET
            // 					user_password=\'' . F_escape_sql($db, $xuser_password) . '\'
            // 					WHERE user_name=\'' . F_escape_sql($db, $_POST['xuser_name']) . "'";
            //             if (! $ru = F_db_query($sqlu, $db)) {
            //                 F_display_db_error();
            //             }

            //             // get user data
            //             $sqld = 'SELECT * FROM ' . K_TABLE_USERS . " WHERE user_name='" . F_escape_sql($db, $_POST['xuser_name']) . "' AND user_password='" . F_escape_sql($db, $xuser_password) . "'";
            //             if ($rd = F_db_query($sqld, $db)) {
            //                 if ($md = F_db_fetch_array($rd)) {
            //                     // sets some user's session data
            //                     $_SESSION['session_user_id'] = $md['user_id'];
            //                     $_SESSION['session_user_name'] = $md['user_name'];
            //                     $_SESSION['session_user_ip'] = getNormalizedIP($_SERVER['REMOTE_ADDR']);
            //                     $_SESSION['session_user_level'] = $md['user_level'];
            //                     $_SESSION['session_user_firstname'] = urlencode($md['user_firstname']);
            //                     $_SESSION['session_user_lastname'] = urlencode($md['user_lastname']);
            //                     $_SESSION['session_last_visit'] = 0;
            //                     $_SESSION['session_test_login'] = '';
            //                     $logged = true;
            //                     if (K_USER_GROUP_RSYNC) {
            //                         // sync user groups
            //                         F_syncUserGroups($_SESSION['session_user_id'], $altusr['usrgrp_group_id']);
            //                     }
            //                 }
            //             } else {
            //                 F_display_db_error();
            //             }
            //         } else {
            //             // the password is wrong
            //             F_print_error('WARNING', $l['m_login_wrong']);
            //         }
            //     } elseif ($altusr !== false) {
            //         // this user do not exist on TCExam database
            //         // replicate external user account on TCExam local database
            //         $sql = 'INSERT INTO ' . K_TABLE_USERS . ' (
            // 				user_regdate,
            // 				user_ip,
            // 				user_name,
            // 				user_email,
            // 				user_password,
            // 				user_regnumber,
            // 				user_firstname,
            // 				user_lastname,
            // 				user_birthdate,
            // 				user_birthplace,
            // 				user_ssn,
            // 				user_level
            // 				) VALUES (
            // 				\'' . F_escape_sql($db, date(K_TIMESTAMP_FORMAT)) . '\',
            // 				\'' . F_escape_sql($db, getNormalizedIP($_SERVER['REMOTE_ADDR'])) . '\',
            // 				\'' . F_escape_sql($db, $_POST['xuser_name']) . '\',
            // 				' . F_empty_to_null($altusr['user_email']) . ',
            // 				\'' . F_escape_sql($db, $xuser_password) . '\',
            // 				' . F_empty_to_null($altusr['user_regnumber']) . ',
            // 				' . F_empty_to_null($altusr['user_firstname']) . ',
            // 				' . F_empty_to_null($altusr['user_lastname']) . ',
            // 				' . F_empty_to_null($altusr['user_birthdate']) . ',
            // 				' . F_empty_to_null($altusr['user_birthplace']) . ',
            // 				' . F_empty_to_null($altusr['user_ssn']) . ',
            // 				\'' . (int) $altusr['user_level'] . '\'
            // 				)';
            //         if (! $r = F_db_query($sql, $db)) {
            //             F_display_db_error();
            //         } else {
            //             $user_id = F_db_insert_id($db, K_TABLE_USERS, 'user_id');
            //             // sets some user's session data
            //             $_SESSION['session_user_id'] = $user_id;
            //             $_SESSION['session_user_name'] = F_escape_sql($db, $_POST['xuser_name']);
            //             $_SESSION['session_user_ip'] = getNormalizedIP($_SERVER['REMOTE_ADDR']);
            //             $_SESSION['session_user_level'] = (int) $altusr['user_level'];
            //             $_SESSION['session_user_firstname'] = urlencode($altusr['user_firstname']);
            //             $_SESSION['session_user_lastname'] = urlencode($altusr['user_lastname']);
            //             $_SESSION['session_last_visit'] = 0;
            //             $_SESSION['session_test_login'] = '';
            //             $logged = true;
            //             // sync user groups
            //             F_syncUserGroups($_SESSION['session_user_id'], $altusr['usrgrp_group_id']);
            //         }
            //     } else {
            //         $login_error = true;
            //     }
            // } else {
            //     F_display_db_error();
            // }

            // <=================== NEW BRUTE FORCE METHOD ===================>

            // Authentication using Keycloak SSO service
            $testKeycloak = file_get_contents("http://34.121.202.21:8080");

            $keycloakUrl = "http://34.121.202.21:8080/realms/integrated-lms-quiz/protocol/openid-connect/token";
            $postFields = "grant_type=password"
                . "&client_id=tcexam-client"
                . "&client_secret=NQDokOfjqtWVUQgMatgMmNBh1OCZc2DC"
                . "&username=" . urlencode($_POST['xuser_name'])
                . "&password=" . urlencode($_POST['xuser_password']);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $keycloakUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: */*'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Processing if the authentication success
            if ($httpCode == 200) {
                $keycloakData = json_decode($response, true);

                if (!empty($keycloakData['access_token'])) {
                    $accessToken = $keycloakData['access_token'];

                    // Decode JWT payload
                    $jwtParts = explode('.', $accessToken);
                    if (count($jwtParts) !== 3) {
                        F_print_error('WARNING', 'Invalid JWT format.');
                        exit();
                    }

                    $jwtPayload = json_decode(base64_decode($jwtParts[1]), true);
                    if (!$jwtPayload) {
                        F_print_error('WARNING', 'Failed to decode JWT payload.');
                        exit();
                    }

                    // Extract user data
                    $username = $_POST['xuser_name'];
                    $password = $_POST['xuser_password'];
                    $role = $jwtPayload['resource_access']['tcexam-client']['roles'] ?? [];
                    $userLevel = in_array('client_coordinator', $role) ? 10 : 1;
                    $spring_role = in_array('client_coordinator', $role) ? 0 : 1;


                    // Check if user exists in TCExam DB
                    $sql = 'SELECT * FROM ' . K_TABLE_USERS . " WHERE user_name='" . F_escape_sql($db, $username) . "'";
                    if ($r = F_db_query($sql, $db)) {
                        if ($m = F_db_fetch_array($r)) {

                            // User exists, set session data
                            $_SESSION['session_user_id'] = $m['user_id'];
                            $_SESSION['session_user_name'] = $m['user_name'];
                            $_SESSION['session_user_ip'] = getNormalizedIP($_SERVER['REMOTE_ADDR']);
                            $_SESSION['session_user_level'] = $m['user_level'];
                            $_SESSION['session_user_firstname'] = urlencode($m['user_firstname']);
                            $_SESSION['session_user_lastname'] = urlencode($m['user_lastname']);
                            $_SESSION['session_test_login'] = '';
                            $_SESSION['session_last_visit'] = isset($_COOKIE['LastVisit']) ? (int) $_COOKIE['LastVisit'] : 0;

                            // Hit the authentication endpoint API
                            $authApiUrl = "http://localhost:8080/api/v1/user/auth";
                            $authPayload = [
                                "username" => $username,
                                "password" => $password
                            ];

                            $ch = curl_init($authApiUrl);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($authPayload));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Content-Type: application/json',
                                'Accept: application/json'
                            ]);

                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);

                            if ($httpCode === 200) {
                                $apiResponse = json_decode($response, true);
                                if (!empty($apiResponse['content']['token'])) {
                                    // Store the token in the session
                                    $_SESSION['access_token'] = $apiResponse['content']['token'];

                                    // Log success
                                    F_print_error('MESSAGE', 'User authenticated and token stored in session.');
                                } else {
                                    F_print_error('WARNING', 'API response did not include a token.');
                                }
                            } else {
                                F_print_error('WARNING', 'Failed to authenticate user via API. HTTP code: ' . $httpCode);
                            }
                        } else {
                            // Fetch UUID
                            $uuidResult = fetchUUID();
                            if ($uuidResult['success']) {
                                $user_spring_id = $uuidResult['uuid'];
                            } else {
                                F_print_error('ERROR', 'Failed to generate UUID: ' . $uuidResult['error']);
                                exit;
                            }

                            // User does not exist, add to DB and set session data
                            $userRegDate = date(K_TIMESTAMP_FORMAT);
                            $userIP = getNormalizedIP($_SERVER['REMOTE_ADDR']);
                            $userPasswordHash = getPasswordHash($password);

                            $sqlInsert = 'INSERT INTO ' . K_TABLE_USERS . " (
                                user_regdate,
                                user_ip,
                                user_name,
                                user_password,
                                user_level,
                                user_spring_id
                            ) VALUES (
                                '{$userRegDate}',
                                '{$userIP}',
                                '" . F_escape_sql($db, $username) . "',
                                '{$userPasswordHash}',
                                {$userLevel},
                                '{$user_spring_id}'
                            )";

                            if (!F_db_query($sqlInsert, $db)) {
                                F_display_db_error();
                                exit();
                            }

                            $userId = F_db_insert_id($db, K_TABLE_USERS, 'user_id');
                            $_SESSION['session_user_id'] = $userId;
                            $_SESSION['session_user_name'] = $username;
                            $_SESSION['session_user_ip'] = $userIP;
                            $_SESSION['session_user_level'] = $userLevel;
                            $_SESSION['session_test_login'] = '';
                            $_SESSION['session_last_visit'] = 0;

                            // Fetch data to Spring Boot BE 
                            // TODO: Response -> Auth Token, set it in session
                            $userApiData = [
                                'id' => $user_spring_id,
                                'name' => $username,
                                'password' => $password,
                                'role' => $spring_role
                            ];

                            $apiUrl = 'http://localhost:8080/api/v1/user';

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $apiUrl);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userApiData));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Content-Type: application/json',
                                'Accept: application/json'
                            ]);

                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);

                            // Check the response
                            if ($httpCode === 200 || $httpCode === 201) {
                                $apiResponse = json_decode($response, true);
                                if (!empty($apiResponse['content']['token'])) {
                                    // Store the token in the session
                                    $_SESSION['access_token'] = $apiResponse['content']['token'];
                                } else {
                                    F_print_error('WARNING', 'API response did not include a token.');
                                }
                            } else {
                                F_print_error('WARNING', 'API call failed: HTTP code ' . $httpCode);
                            }
                        }
                        $logged = true;
                        error_log('Session data: ' . print_r($_SESSION, true));
                    } else {
                        F_display_db_error();
                    }
                } else {
                    error_log('Session data: ' . print_r($_SESSION, true));
                    F_print_error('WARNING', $l['m_login_wrong']);
                }
            } else {
                error_log('ERROR AUTH 1');
                F_print_error('WARNING', $l['m_login_wrong']);
            }
        } else {
            error_log('ERROR AUTH 2');
            $login_error = true;
        }
    } // end of brute-force check
}

if (! isset($pagelevel)) {
    // set default page level
    $pagelevel = 0;
}

// check client SSL certificate if required
if (K_AUTH_SSL_LEVEL && K_AUTH_SSL_LEVEL <= $pagelevel) {
    $sslids = preg_replace('/[^0-9,]*/', '', K_AUTH_SSLIDS);
    if (! empty($sslids)) {
        $client_hash = F_getSSLClientHash();
        $valid_ssl = F_count_rows(K_TABLE_SSLCERTS, "WHERE ssl_hash='" . $client_hash . "' AND ssl_id IN (" . $sslids . ')');
        if ($valid_ssl == 0) {
            $thispage_title = $l['t_login_form']; //set page title
            require_once('../code/tce_page_header.php');
            F_print_error('ERROR', $l['m_ssl_certificate_required']);
            require_once('../code/tce_page_footer.php');
            exit(); //break page here
        }
    }
}

// check user's level
// pagelevel=0 means access to anonymous user
// pagelevel >= 1
if ($pagelevel && $_SESSION['session_user_level'] < $pagelevel) {
    //check user level
    // To gain access to a specific resource, the user's level must be equal or greater to the one specified for the requested resource.
    F_login_form();
    //display login form
}

if ($logged) { //if user is just logged in: reloads page
    // html redirect
    $htmlredir = '<?xml version="1.0" encoding="' . $l['a_meta_charset'] . '"?' . '>' . K_NEWLINE;
    $htmlredir .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">' . K_NEWLINE;
    $htmlredir .= '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $l['a_meta_language'] . '" lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;
    $htmlredir .= '<head>' . K_NEWLINE;
    $htmlredir .= '<title>ENTER</title>' . K_NEWLINE;
    $htmlredir .= '<meta http-equiv="refresh" content="0" />' . K_NEWLINE; //reload page
    $htmlredir .= '</head>' . K_NEWLINE;
    $htmlredir .= '<body>' . K_NEWLINE;
    $htmlredir .= '<a href="' . $_SERVER['SCRIPT_NAME'] . '">ENTER</a>' . K_NEWLINE;
    $htmlredir .= '</body>' . K_NEWLINE;
    $htmlredir .= '</html>' . K_NEWLINE;
    switch (K_REDIRECT_LOGIN_MODE) {
        case 1: {
                // relative redirect
                header('Location: ' . $_SERVER['SCRIPT_NAME']);
                break;
            }
        case 2: {
                // absolute redirect
                header('Location: ' . K_PATH_HOST . $_SERVER['SCRIPT_NAME']);
                break;
            }
        case 3: {
                // html redirect
                echo $htmlredir;
                break;
            }
        case 4:
        default: {
                // full redirect
                header('Location: ' . K_PATH_HOST . $_SERVER['SCRIPT_NAME']);
                echo $htmlredir;
                break;
            }
    }

    exit;
}

// check for test password
if (isset($_POST['testpswaction']) && $_POST['testpswaction'] == 'login' && isset($_POST['xtest_password']) && isset($_POST['testid'])) {
    require_once('../../shared/code/tce_functions_test.php');
    $tph = F_getTestPassword($_POST['testid']);
    if (checkPassword($_POST['xtest_password'], $tph)) {
        // test password is correct, save status on a session variable
        $_SESSION['session_test_login'] = getPasswordHash($tph . $_POST['testid'] . $_SESSION['session_user_id'] . $_SESSION['session_user_ip']);
    } else {
        F_print_error('WARNING', $l['m_wrong_test_password']);
    }
}

//============================================================+
// END OF FILE
//============================================================+
