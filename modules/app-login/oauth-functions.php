<?php
// Copiado tal cual del tema dlp (login/registro desde la app). Único cambio: isset() en el autologin para evitar el aviso de PHP.
add_action( "wp_ajax_nopriv_custom_register_from_app", "custom_register_from_app");
add_action( "wp_ajax_custom_register_from_app", "custom_register_from_app");


function custom_register_from_app() {

    if($_POST['hash']) {

        $string = explode(":::", base64_decode($_POST['hash']));
        $u = $string[0];
        $p = $string[1];
        $e = $string[2];
        $t = $string[3];

        if($_POST['from'] == "social") {
            $p = "login-" . $p;
        }


        $r = this_mail_already_exists($e);

        if( $r ) {

            $creds = array();
            $creds['user_login'] = $e;
            $creds['user_password'] = $p;
            $creds['remember'] = false;

            $signon = wp_signon($creds, false);

            if (is_wp_error($signon)) {

                $data = [
                    "result"    => "error",
                    "signon"    => $signon,
                    "message"   => "Credenciales incorrectas"
                ];
                
            } else {
                $data = [
                    "result"    => "success",
                    "already"   => "true",
                    "user"      => $r
                ];
            }

            wp_send_json( $data, 200 );
            
        }

        $user_data = array(
            'user_pass'         => $p,
            'user_login'        => $e,
            'user_nicename'     => $u,
            'user_email'        => $e,
            'display_name'      => $u,

        );

        $usr_result = wp_insert_user($user_data);
        if(is_wp_error($usr_result)){
            $error = $usr_result->get_error_message();
            $data = [
                "result"    => "error",
                "message"   => $error
            ];
        }else{
            update_user_meta( $usr_result, "billing_phone", $t);
            $user = get_user_by('id', $usr_result);
            $data = [
                "result"    => "success",
                "already"   => "false",
                "user"      => $user
            ];
        }
    } else {
        $data = [
            "result"    => "error",
            "message"   => "Información insuficiente"
        ];
    }

    wp_send_json( $data, 200 );
}



function this_mail_already_exists($email) {
    $user = get_user_by('email', $email);
    return $user;
}

if(isset($_GET['autologin'], $_GET['hash']) && $_GET['autologin'] == 1 && $_GET['hash']) {
    
    $autologin_string = explode(":::", base64_decode($_GET['hash']));
    $autologin_u = $autologin_string[0];
    $autologin_p = $autologin_string[1];
    $autologin_e = $autologin_string[2];

    $autologin_device = "Android";

    if(isset($_GET['from']) && $_GET['from'] == "social") {
        $autologin_p = "login-" . $autologin_p;
    }

    if(isset($_GET['device']) && $_GET['device'] == "IOS") {
        $autologin_device = "IOS";
    }

    /*
    if(is_user_logged_in()) {
        wp_redirect( home_url() . "/?origin=webview&device=" . $autologin_device, 302 );
        die();
    }
    */

    
    $autologin_creds = array();
    $autologin_creds['user_login'] = $autologin_e;
    $autologin_creds['user_password'] = $autologin_p;
    $autologin_creds['remember'] = false;
    try {
        @wp_signon($autologin_creds, false);
    } catch (Exception $err) {
        var_dump($err);
    }
    
    //wp_redirect( home_url() . "/?origin=webview&device=" . $autologin_device, 302 );
    //die();
}



function ws_custom_register_from_app(WP_REST_Request $request) {

    if($request->get_param( 'hash' )) {

        $string = explode( ":::", base64_decode( $request->get_param('hash') ) );
        $u = $string[0];
        $p = $string[1];
        $e = $string[2];
        $t = $string[3];

        if($_POST['from'] == "social") {
            $p = "login-" . $p;
        }


        $r = this_mail_already_exists($e);

        if( $r ) {

            $creds = array();
            $creds['user_login'] = $e;
            $creds['user_password'] = $p;
            $creds['remember'] = false;

            $signon = wp_signon($creds, false);

            if (is_wp_error($signon)) {

                $data = [
                    "result"    => "error",
                    "signon"    => $signon,
                    "message"   => "Credenciales incorrectas"
                ];
                
            } else {
                $data = [
                    "result"    => "success",
                    "already"   => "true",
                    "user"      => $r
                ];
            }

            wp_send_json( $data, 200 );
            
        }

        $user_data = array(
            'user_pass'         => $p,
            'user_login'        => $e,
            'user_nicename'     => $u,
            'user_email'        => $e,
            'display_name'      => $u
        );

        $usr_result = wp_insert_user($user_data);
        if(is_wp_error($usr_result)){
            $error = $usr_result->get_error_message();
            $data = [
                "result"    => "error",
                "message"   => $error
            ];
        }else{
            update_user_meta( $usr_result, "billing_phone", $t);
            $user = get_user_by('id', $usr_result);
            $data = [
                "result"    => "success",
                "already"   => "false",
                "user"      => $user
            ];
        }
    } else {
        $data = [
            "result"    => "error",
            "message"   => "Información insuficiente"
        ];
    }

    wp_send_json( $data, 200 );
}




add_action( 'rest_api_init', function () {
  register_rest_route( 'auth/v1', '/register_from_app/', array(
    'methods' => 'GET',
    'callback' => 'ws_custom_register_from_app',
  ) );
} );