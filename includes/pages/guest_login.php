<?php

use Engelsystem\Database\DB;

/**
 * @return string
 */
function login_title()
{
    return __('Login');
}

/**
 * @return string
 */
function register_title()
{
    return __('Register');
}

/**
 * @return string
 */
function logout_title()
{
    return __('Logout');
}

/**
 * Engel registrieren
 *
 * @return string
 */
function guest_register()
{
    global $user, $privileges;
    $tshirt_sizes = config('tshirt_sizes');
    $enable_tshirt_size = config('enable_tshirt_size');
    $min_password_length = config('min_password_length');
    $event_config = EventConfig();
    $request = request();
    $session = session();

    $msg = '';
    $nick = '';
    $lastName = '';
    $preName = '';
    $age = 0;
    $tel = '';
    $dect = '';
    $mobile = '';
    $mail = '';
    $email_shiftinfo = true;
    $email_by_human_allowed = true;
    $jabber = '';
    $hometown = '';
    $comment = '';
    $tshirt_size = '';
    $password_hash = '';
    $selected_angel_types = [];
    $planned_arrival_date = null;

    $angel_types_source = AngelTypes();
    $angel_types = [];
    foreach ($angel_types_source as $angel_type) {
        $angel_types[$angel_type['id']] = $angel_type['name'] . ($angel_type['restricted'] ? ' (restricted)' : '');
        if (!$angel_type['restricted']) {
            $selected_angel_types[] = $angel_type['id'];
        }
    }

    if (!in_array('register', $privileges) || (!isset($user) && !config('registration_enabled'))) {
        error(__('Registration is disabled.'));

        return page_with_title(register_title(), [
            msg(),
        ]);
    }

    if ($request->has('submit')) {
        $valid = true;

        if ($request->has('nick') && strlen(User_validate_Nick($request->input('nick'))) > 1) {
            $nick = User_validate_Nick($request->input('nick'));
            if (count(DB::select('SELECT `UID` FROM `User` WHERE `Nick`=? LIMIT 1', [$nick])) > 0) {
                $valid = false;
                $msg .= error(sprintf(__('Your nick &quot;%s&quot; already exists.'), $nick), true);
            }
        } else {
            $valid = false;
            $msg .= error(sprintf(
                __('Your nick &quot;%s&quot; is too short (min. 2 characters).'),
                User_validate_Nick($request->input('nick'))
            ), true);
        }

        if ($request->has('mail') && strlen(strip_request_item('mail')) > 0) {
            $mail = strip_request_item('mail');
            if (!check_email($mail)) {
                $valid = false;
                $msg .= error(__('E-mail address is not correct.'), true);
            }
        } else {
            $valid = false;
            $msg .= error(__('Please enter your e-mail.'), true);
        }

        if ($request->has('email_shiftinfo')) {
            $email_shiftinfo = true;
        }

        if ($request->has('email_by_human_allowed')) {
            $email_by_human_allowed = true;
        }
        if ($request->has('jabber') && strlen(strip_request_item('jabber')) > 0) {
            $jabber = strip_request_item('jabber');
            if (!check_telegram($jabber)) {
                $valid = false;
                $msg .= error(__('Please check your telegram account information.'), true);
            }
        }

        if ($enable_tshirt_size) {
            if ($request->has('tshirt_size') && isset($tshirt_sizes[$request->input('tshirt_size')])) {
                $tshirt_size = $request->input('tshirt_size');
            } else {
                $valid = false;
                $msg .= error(__('Please select your shirt size.'), true);
            }
        }

        if ($request->has('password') && strlen($request->postData('password')) >= $min_password_length) {
            if ($request->postData('password') != $request->postData('password2')) {
                $valid = false;
                $msg .= error(__('Your passwords don\'t match.'), true);
            }
        } else {
            $valid = false;
            $msg .= error(sprintf(
                __('Your password is too short (please use at least %s characters).'),
                $min_password_length
            ), true);
        }

        if ($request->has('planned_arrival_date')) {
            $tmp = parse_date('Y-m-d H:i', $request->input('planned_arrival_date') . ' 00:00');
            $result = User_validate_planned_arrival_date($tmp);
            $planned_arrival_date = $result->getValue();
            if (!$result->isValid()) {
                $valid = false;
                error(__('Please enter your planned date of arrival. It should be after the buildup start date and before teardown end date.'));
            }
        } else {
            $valid = false;
            error(__('Please enter your planned date of arrival. It should be after the buildup start date and before teardown end date.'));
        }

        $selected_angel_types = [];
        foreach (array_keys($angel_types) as $angel_type_id) {
            if ($request->has('angel_types_' . $angel_type_id)) {
                $selected_angel_types[] = $angel_type_id;
            }
        }

        // Trivia
        if ($request->has('lastname') && strlen(strip_request_item('lastname')) > 0) {
	    $lastName = strip_request_item('lastname');
        } else {
            $valid = false;
            $msg .= error(_("Please enter your last name."), true);
        }
        if ($request->has('prename')&& strlen(strip_request_item('prename')) > 0) {
	    $preName = strip_request_item('prename');
        } else {
            $valid = false;
            $msg .= error(_("Please enter your first name."), true);
        }
        if ($request->has('mobile')) {
            $mobile = strip_request_item('mobile');
        }
        if ($request->has('hometown') && strlen(strip_request_item('hometown')) > 0) {
            $hometown = strip_request_item('hometown');
        } else {
            $valid = false;
            $msg .= error(_("Please enter your hometown."), true);
        }
        if ($request->has('comment')) {
            $comment = strip_request_item_nl('comment');
        }

        if ($valid) {
            DB::insert('
                    INSERT INTO `User` (
                        `color`,
                        `Nick`,
                        `Vorname`,
                        `Name`,
                        `Alter`,
                        `Telefon`,
                        `DECT`,
                        `Handy`,
                        `email`,
                        `email_shiftinfo`,
                        `email_by_human_allowed`,
                        `jabber`,
                        `Size`,
                        `Passwort`,
                        `kommentar`,
                        `Hometown`,
                        `CreateDate`,
                        `Sprache`,
                        `arrival_date`,
                        `planned_arrival_date`,
                        `force_active`,
                        `lastLogIn`,
                        `api_key`,
                        `got_voucher`
                    )
                    VALUES  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NULL, ?, TRUE, 0, "", 0)
                ',
                [
                    config('theme'),
                    $nick,
                    $preName,
                    $lastName,
                    $age,
                    $tel,
                    $dect,
                    $mobile,
                    $mail,
                    (int)$email_shiftinfo,
                    (int)$email_by_human_allowed,
                    $jabber,
                    $tshirt_size,
                    $password_hash,
                    $comment,
                    $hometown,
                    $session->get('locale'),
                    $planned_arrival_date,
                ]
            );

            // Assign user-group and set password
            $user_id = DB::getPdo()->lastInsertId();
            DB::insert('INSERT INTO `UserGroups` (`uid`, `group_id`) VALUES (?, -20)', [$user_id]);
            set_password($user_id, $request->postData('password'));

            // Assign angel-types
            $user_angel_types_info = [];
            foreach ($selected_angel_types as $selected_angel_type_id) {
                DB::insert(
                    'INSERT INTO `UserAngelTypes` (`user_id`, `angeltype_id`, `supporter`) VALUES (?, ?, FALSE)',
                    [$user_id, $selected_angel_type_id]
                );
                $user_angel_types_info[] = $angel_types[$selected_angel_type_id];
            }

            engelsystem_log(
                'User ' . User_Nick_render(User($user_id))
                . ' signed up as: ' . join(', ', $user_angel_types_info)
            );
            success(__('Angel registration successful!'));

            // User is already logged in - that means a supporter has registered an angel. Return to register page.
            if (isset($user)) {
                redirect(page_link_to('register'));
            }

            // If a welcome message is present, display registration success page.
            if (!empty($event_config) && !empty($event_config['event_welcome_msg'])) {
                return User_registration_success_view($event_config['event_welcome_msg']);
            }

            redirect(page_link_to('/'));
        }
    }

    $buildup_start_date = time();
    $teardown_end_date = null;
    if (!empty($event_config)) {
        if (isset($event_config['buildup_start_date'])) {
            $buildup_start_date = $event_config['buildup_start_date'];
        }
        if (isset($event_config['teardown_end_date'])) {
            $teardown_end_date = $event_config['teardown_end_date'];
        }
    }

    return page_with_title(register_title(), [
        __('By completing this form you\'re registering as a Chaos-Angel. This script will create you an account in the angel task scheduler.'),
        $msg,
        msg(),
        form([
            div('row', [
                div('col-md-6', [
                    div('row', [
                        div('col-sm-4', [
                            form_text('nick', __('Nick') . ' ' . entry_required(), $nick)
                        ]),
                        div('col-sm-8', [
                            form_email('mail', __('E-Mail') . ' ' . entry_required(), $mail)
                        ])
                    ]),
                    div('row', [
                        div('col-sm-6', [
                            form_date(
                                'planned_arrival_date',
                                __('Planned date of arrival') . ' ' . entry_required(),
                                $planned_arrival_date, $buildup_start_date, $teardown_end_date
                            )
                        ]),
                        div('col-sm-6', [
                            $enable_tshirt_size ? form_select('tshirt_size',
                                __('Shirt size') . ' ' . entry_required(),
                                $tshirt_sizes, $tshirt_size, __('Please select...')) : ''
                        ])
                    ]),
                    div('row', [
                        div('col-sm-6', [
                            form_password('password', __('Password') . ' ' . entry_required())
                        ]),
                        div('col-sm-6', [
                            form_password('password2', __('Confirm password') . ' ' . entry_required())
                        ])
                    ]),
                    form_checkboxes(
                        'angel_types',
                        __('What do you want to do?') . sprintf(
                            ' (<a href="%s">%s</a>)',
                            page_link_to('angeltypes', ['action' => 'about']),
                            __('Description of job types')
                        ),
                        $angel_types,
                        $selected_angel_types
                    ),
                    form_info(
                        '',
                        __('Restricted angel types need will be confirmed later by a supporter. You can change your selection in the options section.')
                    )
                ]),
                div('col-md-6', [
                    div('row', [
                        div('col-sm-4', [
                            form_text('mobile', __('Mobile (only used for important problems)'), $mobile)
                        ]),
                    ]),
                    div('row', [
                        div('col-sm-6', [
                            form_text('prename', __('First name'). ' ' . entry_required(), $preName)
                        ]),
                        div('col-sm-6', [
                            form_text('lastname', __('Last name'). ' ' . entry_required(), $lastName)
                        ])
                    ]),
                    div('row', [
                        div('col-sm-9', [
                            form_text('hometown', __('Hometown'). ' ' . entry_required(), $hometown)
                        ])
                    ]),
                    form_info(entry_required() . ' = ' . __('Entry required!'))
                ])
            ]),
            form_submit('submit', __('Register'))
        ])
    ]);
}

/**
 * @return string
 */
function entry_required()
{
    return '<span class="text-info glyphicon glyphicon-warning-sign"></span>';
}

/**
 * @return bool
 */
function guest_logout()
{
    session()->invalidate();
    redirect(page_link_to('start'));
    return true;
}

/**
 * @return string
 */
function guest_login()
{
    $nick = '';
    $request = request();
    $session = session();
    $valid = true;

    $session->remove('uid');

    if ($request->has('submit')) {
        if ($request->has('nick') && strlen(User_validate_Nick($request->input('nick'))) > 0) {
            $nick = User_validate_Nick($request->input('nick'));
            $login_user = DB::selectOne('SELECT * FROM `User` WHERE `Nick`=?', [$nick]);
            if (!empty($login_user)) {
                if ($request->has('password')) {
                    if (!verify_password($request->postData('password'), $login_user['Passwort'], $login_user['UID'])) {
                        $valid = false;
                        error(__('Your password is incorrect.  Please try it again.'));
                    }
                } else {
                    $valid = false;
                    error(__('Please enter a password.'));
                }
            } else {
                $valid = false;
                error(__('No user was found with that Nickname. Please try again. If you are still having problems, ask a Dispatcher.'));
            }
        } else {
            $valid = false;
            error(__('Please enter a nickname.'));
        }

        if ($valid && !empty($login_user)) {
            $session->set('uid', $login_user['UID']);
            $session->set('locale', $login_user['Sprache']);

            redirect(page_link_to('news'));
        }
    }

    $event_config = EventConfig();

    return page([
        div('col-md-12', [
            div('row', [
                EventConfig_countdown_page($event_config)
            ]),
            div('row', [
                div('col-sm-6 col-sm-offset-3 col-md-4 col-md-offset-4', [
                    div('panel panel-primary first', [
                        div('panel-heading', [
                            '<span class="icon-icon_angel"></span> ' . __('Login')
                        ]),
                        div('panel-body', [
                            msg(),
                            form([
                                form_text_placeholder('nick', __('Nick'), $nick),
                                form_password_placeholder('password', __('Password')),
                                form_submit('submit', __('Login')),
                                !$valid ? buttons([
                                    button(page_link_to('user_password_recovery'), __('I forgot my password'))
                                ]) : ''
                            ])
                        ]),
                        div('panel-footer', [
                            glyph('info-sign') . __('Please note: You have to activate cookies!')
                        ])
                    ])
                ])
            ]),
            div('row', [
                div('col-sm-6 text-center', [
                    heading(register_title(), 2),
                    get_register_hint()
                ]),
                div('col-sm-6 text-center', [
                    heading(__('What can I do?'), 2),
                    '<p>' . __('Please read about the jobs you can do to help us.') . '</p>',
                    buttons([
                        button(
                            page_link_to('angeltypes', ['action' => 'about']),
                            __('Teams/Job description') . ' &raquo;'
                        )
                    ])
                ])
            ])
        ])
    ]);
}

/**
 * @return string
 */
function get_register_hint()
{
    global $privileges;

    if (in_array('register', $privileges) && config('registration_enabled')) {
        return join('', [
            '<p>' . __('Please sign up, if you want to help us!') . '</p>',
            buttons([
                button(page_link_to('register'), register_title() . ' &raquo;')
            ])
        ]);
    }

    return error(__('Registration is disabled.'), true);
}
