<?php

    // NOT ALLOW DIRECT ACCESS
    if ( $_SERVER["REQUEST_METHOD"] == "GET" && realpath(__FILE__) == realpath( $_SERVER["SCRIPT_FILENAME"] ) )
    {
        header( "HTTP/1.0 403 Forbidden", TRUE, 403 );
        die( header( "location: https://gaspariniodontologia.com.br/agenda/" ) );
        // die( header( "location: http://192.168.15.8:8000/" ) );
    }


    const URL      = "https://api.personal-ed.com.br";
    const CODE     = "gasparini";

    const NPREST   = 1; // 1 == gasparini
    const NUNID    = 1; // sempre 1
    const NROPAC   = 0; // novo paciente
    const NTPFONE1 = 4; // tipo celular

    function treatInput( $data )
    {
        return htmlspecialchars( stripslashes( trim( $data ) ) );
    }

    $data = [
        "nprest"   => NPREST,   // NPREST,
        "nunid"    => NUNID,    // NUNID,
        "nropac"   => NROPAC,   // NROPAC,
        "ntpfone1" => NTPFONE1, // NTPFONE1,

        "dt_data"  => treatInput($_POST["dt_data"]),  // date,
        "shorario" => treatInput($_POST["shorario"]), // hour,
        "snome"    => treatInput($_POST["snome"]),    // name,
        "sfone1"   => treatInput($_POST["sfone1"]),   // phone,
        "smotivo"  => treatInput($_POST["smotivo"]),  // email
    ];

    if (empty($data["dt_data"]) || empty($data["shorario"]) || empty($data["snome"]) || empty($data["sfone1"]) || empty($data["smotivo"]))
    {
        // FAIL
        echo json_encode([
            "code"     => 404,
            "error"    => true,
            "success"  => false,
            "message"  => "Invalid data",
        ]);
        exit();
    }

    $ch = curl_init();

    if ( !$ch )
    {
        // FAIL
        echo json_encode([
            "code"     => "I-500",
            "error"    => true,
            "success"  => false,
            "message"  => "Unable to connect to calendar service!",
        ]);
        exit();
    }

    curl_setopt_array( $ch, array(
        CURLOPT_URL            => URL . "/" . CODE . "/RPCCreateAgenda",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => "",
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => "POST",
        CURLOPT_HTTPHEADER     => [ "Content-type: application/json" ],
        CURLOPT_POSTFIELDS     => json_encode( $data ),
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ));



    $response = curl_exec( $ch );

    if ( empty( $response ) )
    {
        $code    = "E-500";
        $error   = true;
        $success = false;
        $message = curl_error( $ch ) . ". exec";
    }
    else
    {
        $info = curl_getinfo( $ch );

        if ( empty( $info["http_code"] ) )
        {
            $code    = "NR-500";
            $error   = true;
            $success = false;
            $message = "No HTTP code was returned. exec";
        }
        else if ( $info["http_code"] >= 200 && $info["http_code"] <= 206 )
        {
            $code    = 200;
            $error   = false;
            $success = true;
            $message = "Successful";
        }
        else
        {
            $code    = $info["http_code"];
            $error   = true;
            $success = false;
            $message = "Server return with error. exec";
        }
    }

    curl_close( $ch );

    if ( $code != 200 )
    {
        // FAIL
        echo json_encode([
            "code"     => $code,
            "error"    => $error,
            "error"    => $success,
            "message"  => $message
        ]);
        exit();
    }

    $response = json_decode( $response, true );

    if ( !isset( $response["error"] ) && isset( $response["dados"]["ret"] ) )
    {
        $response = $response["dados"]["ret"];

        if ( $response["VL"] === "0" )
        {
            $response = [
                "code"     => 500,
                "error"    => true,
                "success"  => false,
                "message"  => "Schedule not recorded, check if schedule is available. Datetime: " . $data["dt_data"] . " " . $data["shorario"],
                "full"     => $response
            ];
        }
        else
        {
            $response = [
                "code"     => $code,
                "error"    => false,
                "success"  => true,
                "message"  => "Schedule saved successfully. Datetime: " . $data["dt_data"] . " " . $data["shorario"],
            ];
        }

        // SUCCESS
        echo json_encode( $response );
        exit();
    }
    else
    {
        // FAIL
        echo json_encode([
            "code"     => $code,
            "error"    => true,
            "success"  => false,
            "message"  => "Invalid API return"
        ]);
        exit();
    }
?>
