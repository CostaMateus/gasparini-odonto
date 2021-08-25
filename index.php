<?php

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: origin, x-requested-with, content-type");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

    date_default_timezone_set("America/Sao_Paulo");

    // const NPREST = 277; // 277 mateus
    // const HOURS = [ "08:00", "09:00", "10:00", "11:00", "14:00", "15:00", "16:00", "17:00", "18:00" ];

    const NPREST = 1;   // 1 gasparini
    const NUNID  = 1;   // sempre 1

    const URL   = "https://api.personal-ed.com.br";
    const CODE  = "gasparini";
    const HOURS = [ "09:00", "10:00", "11:00", "14:00", "15:00", "16:00", "17:00" ];

    /**
     * Recupera a agenda do Dr Gasparini
     *
     * @return array
     */
    function getScheduleGasparini()
    {
        $ch = curl_init();

        if (!$ch) return [
            "code"     => "I-500",
            "error"    => true,
            "message"  => "Não foi possível se conectar ao serviço de agenda!",
            "response" => ""
        ];

        curl_setopt_array( $ch, array(
            CURLOPT_URL            => URL . "/" . CODE . "/RPCGetHorariosLivres",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_HTTPHEADER     => [ "Content-type: application/json" ],
            CURLOPT_POSTFIELDS     => json_encode([
                "dt_data_ini" => date("Y-m-d"),
                "dt_data_fim" => date("Y-m-d", strtotime(date("Y-m-d") . " +16 days")),
                "nprest"      => NPREST,
                "nunid"       => NUNID
            ])
        ));

        $response = curl_exec($ch);

        if (empty($response))
        {
            $code    = "E-500";
            $error   = true;
            $message = curl_error($ch);
        }
        else
        {
            $info = curl_getinfo($ch);

            if (empty($info["http_code"]))
            {
                $code    = "NR-500";
                $error   = true;
                $message = "No HTTP code was returned";
            }
            else if ($info["http_code"] >= 200 && $info["http_code"] <= 206)
            {
                $code    = $info["http_code"];
                $error   = false;
                $message = "Successful";
            }
            else
            {
                $code    = $info["http_code"];
                $error   = true;
                $message = "Server return with error";
            }
        }

        curl_close( $ch );

        if ($code < 200 || $code > 206)
        {
            return [
                "code"     => $code,
                "error"    => $error,
                "message"  => $message,
                "response" => ""
            ];
        }

        $response = json_decode( $response, true );

        if (isset($response["dados"]["ret"]["TX"]))
        {
            $message = "Agenda indisponível";
            return [
                "code"     => $code,
                "error"    => $error,
                "message"  => $message,
                "response" => ""
            ];
        }

        return [
            "code"     => $code,
            "error"    => $error,
            "message"  => $message,
            "response" => $response["dados"]["ret"]
        ];
    }

    /**
     * Trata os horários recebidos da API
     *
     * @param array $schedules
     * @return array
     */
    function treatValidSchedule($schedules)
    {
        $consultedDays = [];
        foreach (range(0,15,1) as $i)
        {
            $today = date("Y-m-d");
            $date  = date("Y-m-d", strtotime("$today +$i day"));
            $consultedDays[$date] = [];
        }

        $temp = [];

        foreach ($consultedDays as $id => $x)
        {
            foreach ($schedules as $sch)
            {
                $hour    = $sch["HORARIO"];

                // Horarios de atendimento
                if (!in_array($hour, HOURS)) continue;


                $date    = formatDate($id);
                $dayWeek = getDayOfWeek($id);
                $day     = isTodayTomorrow($id, $dayWeek);

                $temp[$date]["date"]    = $id;
                $temp[$date]["day"]     = $day;
                $temp[$date]["dayWeek"] = $dayWeek;


                if ($id != $sch["DATA"])
                {
                    if (!isset($temp[$date]["hours"])) $temp[$date]["hours"] = [];

                    continue;
                }

                // Na segunda, não atende pela manhã
                if ($dayWeek == "Seg" && in_array($hour, ["09:00", "10:00", "11:00"])) continue;

                // Ter/Qui/Sex todos horarios menos 17h
                if ($dayWeek != "Seg" && $dayWeek != "Qua" && $hour == "17:00") continue;

                $temp[$date]["hours"][] = $hour;
            }
        }

        return $temp;
    }

    /**
     * Retorna qual o dia da semana
     *
     * @param string $date
     * @return string
     */
    function getDayOfWeek($date)
    {
        $day = date("l", strtotime($date));
        switch ($day)
        {
            case "Sunday":
                return "Dom";
            break;
            case "Monday":
                return "Seg";
            break;
            case "Tuesday":
                return "Ter";
            break;
            case "Wednesday":
                return "Qua";
            break;
            case "Thursday":
                return "Qui";
            break;
            case "Friday":
                return "Sex";
            break;
            case "Saturday":
                return "Sáb";
            break;
        }
    }

    /**
     * Transforma data e hora em um id unico
     *
     * @param string $date
     * @param string $hour
     * @return string
     */
    function getId($date, $hour)
    {
        $hour = explode(":", $hour);
        return str_replace("/", "_", $date) . "_" . $hour[0];
    }

    /**
     * Verifica se a data é hoje ou amanhã
     *
     * @param string $date
     * @param string $dayWeek
     * @return string
     */
    function isTodayTomorrow($date, $dayWeek)
    {
        if ($date == date("Y-m-d"))
            return "Hoje";
        else if ($date == date("Y-m-d", strtotime(date("Y-m-d") . " +1 day")))
            return "Amanhã";
        else
            return $dayWeek;
    }

    /**
     * Formata data de MM-DD para DD/MM
     *
     * @param string $date
     * @return string
     */
    function formatDate($date)
    {
        return implode("/", array_reverse(explode("-", $date)));
    }

    /**
     * Remove o ano da data
     *
     * @param string $date
     * @return string
     */
    function onlyDayMonth($date)
    {
        $date = explode("/", $date);
        return $date[0] . "/" . $date[1];
    }


    $data  = getScheduleGasparini();
    $code  = $data["code"];
    $error = $data["error"];

    $schedules = ($code == 200) ? treatValidSchedule($data["response"]) : "" ;

    // print_r ("<pre>");
    // print_r ($schedules);
    // print_r ("</pre>");
    // exit();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS base -->
    <!-- <link rel="stylesheet" href="https://gaspariniodontologia.com.br/wp-content/cache/autoptimize/css/autoptimize_3dc9b0de17d877e81f045a2e56d3a6dc.css"> -->
    <link rel="stylesheet" href="https://gaspariniodontologia.com.br/agenda/index.css" >
    <!-- Icons base -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" >

    <!-- Favicon -->
    <link rel="icon" href="https://gaspariniodontologia.com.br/wp-content/uploads/2019/02/cropped-favicon-1-32x32.png" sizes="32x32">
    <link rel="icon" href="https://gaspariniodontologia.com.br/wp-content/uploads/2019/02/cropped-favicon-1-192x192.png" sizes="192x192">
    <link rel="apple-touch-icon-precomposed" href="https://gaspariniodontologia.com.br/wp-content/uploads/2019/02/cropped-favicon-1-180x180.png">
    <meta name="msapplication-TileImage" content="https://gaspariniodontologia.com.br/wp-content/uploads/2019/02/cropped-favicon-1-270x270.png">

    <title>Agenda - Dentista na Mooca Gasparini Odontologia Referência Implantes</title>

    <style>
        .fs-12 {
            font-size: 12px;
        }
        .btn-check:active+.btn-outline-gasparini,
        .btn-check:checked+.btn-outline-gasparini,
        .btn-outline-gasparini.active,
        .btn-outline-gasparini.dropdown-toggle.show,
        .btn-outline-gasparini:active {
            color: #FFF !important;
            background-color: #51C5D2 !important;
            border-color: #51C5D2 !important;
        }
        .btn-outline-gasparini {
            color: #51C5D2 !important;
            background-color: #FFF !important;
            border-color: #51C5D2 !important;
        }
        .btn-gasparini {
            color: #FFF !important;
            background-color: #51C5D2 !important;
        }
        .text-gasparini {
            color: #51C5D2;
        }

        .spin-load {
            width: 45px;
            height: 45px;
            margin: auto 5px;
            border: 3px solid #F2F2F2;
            border-top: 3px solid #AEAEAE;
            border-radius: 50%;
            -webkit-animation: spin 1s linear infinite; /* Safari */
            animation: spin 1s linear infinite;
        }
        @-webkit-keyframes spin {
            0% { -webkit-transform: rotate(0deg); }
            100% { -webkit-transform: rotate(360deg); }
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /*
        .invert {
            background-color: #484848;
            color: #A9A9A9;
        }
        .invert a,
        .invert h6,
        .footer-area a,
        .footer-container a{
            color: #FFF;
        }
        .invert h6 {
            font-weight: lighter;
        }
        .footer-area.widget-area {
            padding-top: 20px;
            padding-bottom: 20px;
        }
        .footer-area {
            color: rgba(169,169,169,.7);
        }
        .footer-area {
            font-size: 14px;
            font-size: .875rem;
            line-height: 1.7;
        }
        @media (min-width: 768px) {
            .widget-area .widget {
                margin-bottom: 20px;
            }
        }
        .widget-area .widget {
            margin-bottom: 3em;
        }
        .footer-area.widget-area .widget {
            margin: 10px 0 0;
        }
        .footer-area aside {
            margin-top: 10px;
            margin-bottom: 20px;
        }
        #colophon a:hover {
            color: #51C5D2;
        }
        .top-panel.invert {
            color: #A9A9A9;
            background-color: #FFF;
        }
        .top-panel {
            font-size: 12px;
            font-size: .75rem;
            box-shadow: 0 0 10px rgb(0 0 0 / 10%);
        }
        .top-panel__message .info-block i {
            font-size: 16px;
            font-size: 1rem;
            padding-right: 10px;
        }
        .top-panel__message .info-block {
            display: inline-block;
        }
        .top-panel__message > * {
            margin: 7px;
        }
        .top-panel__message {
            margin-left: -7px;
        }
        */

        #modalEnd h5, #div-header-schedule h5 {
            font-family: "Open Sans", sans-serif !important;
            font-size: 21px;
        }
        .open-sans.text-uppercase {
            font-family: "Open Sans",sans-serif;
        }
        #colophon {
            -webkit-text-size-adjust: 100%;
            --qlwapp-scheme-font-family: Calibri;
            --qlwapp-scheme-font-size: 18;
            --qlwapp-scheme-brand: #2DB443;
            --qlwapp-scheme-qlwapp_scheme_form_nonce: d8df549bdb;
            font-style: normal;
            font-weight: 500;
            line-height: 1.5;
            font-family: Arial,Helvetica,sans-serif;
            letter-spacing: 0px;
            text-align: left;
            font-size: 16px;
            box-sizing: inherit;
        }
        .footer-container {
            padding: 18px 0 !important;
            background-color: #424242;
        }
        .footer-copyright {
            font-size: 14px;
            font-size: .875rem;
            color: #A9A9A9;
        }
        .footer-container a{
            color: #FFF;
        }
        .footer-container a:hover {
            color: #51C5D2;
        }

        .float-right {
            float: right !important;
        }
    </style>
</head>
<body class="bg-light" >

    <header id="masthead" class="site-header minimal" role="banner">
        <div class="top-panel invert">
            <div class="top-panel-container container">
                <div class="top-panel__wrap">
                    <div class="top-panel__message">
                        <div class="info-block">
                            <i class="fa fa-envelope text-gasparini"></i>Atendimento: 8:00 - 18:00 (segunda à sexta) - CROSP/CL 11414. RT-CRO 56196. - José Luiz B. Gasparini
                        </div>
                        <div class="info-block">
                            <i class="fa fa-mobile-alt text-gasparini"></i>Agende sua consulta via WhatsApp:<a href="tel:#"><strong> (11) 97755-6501 </strong></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="header-wrapper">
            <div class="header-container container">
                <div class="header-container_wrap">
                    <div class="header-container__flex">
                        <div class="site-branding">
                            <div class="site-logo"><a class="site-logo__link" href="https://gaspariniodontologia.com.br/" rel="home"><img src="https://gaspariniodontologia.com.br/wp-content/uploads/2019/02/dentista-mooca.jpg" alt="Gasparini Odontologia | Dentista na Mooca | Clínica Odontológica" class="site-link__img"></a></div>
                        </div>
                        <nav id="site-navigation" class="main-navigation stuckMenu" role="navigation" style="position: relative; top: 0px;">
                            <button class="menu-toggle" aria-controls="main-menu" aria-expanded="false">
                                <i class="menu-toggle__icon fa fa-bars"></i>
                                <i class="menu-off__icon fa fa-times"></i>
                                <span>Menu</span>
                            </button>
                            <div class="main-menu__wrap">
                                <ul id="main-menu" class="menu">
                                    <li id="menu-item-35" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-home menu-item-35">
                                        <a href="https://gaspariniodontologia.com.br">Home</a>
                                    </li>
                                    <li id="menu-item-20" class="menu-item menu-item-type-post_type menu-item-object-page page_item page-item-4280 menu-item-20">
                                        <a href="https://gaspariniodontologia.com.br/clinica-odontologica-mooca/">A Clínica</a>
                                    </li>
                                    <li id="menu-item-4669" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-has-children menu-item-4669">
                                        <span class="sub-menu-toggle"></span>
                                        <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/">Tratamentos</a>
                                        <ul class="sub-menu">
                                            <li id="menu-item-4670" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4670">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/primeira-consulta-dentista-mooca/">Primeira Consulta Odontológica</a>
                                            </li>
                                            <li id="menu-item-4671" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4671">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/endodontia-tratamento-canal-mooca/">Endodontia</a>
                                            </li>
                                            <li id="menu-item-4672" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4672">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/estetica-dental-mooca/">Estética Dental</a>
                                            </li>
                                            <li id="menu-item-4673" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4673">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/protese-dentaria/">Prótese Dentária</a>
                                            </li>
                                            <li id="menu-item-4674" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4674">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/implantodontia-implantes-odontologicos/">Implantes Dentários</a>
                                            </li>
                                            <li id="menu-item-4675" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4675">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/manutencao-preventiva/">Manutenção preventiva</a>
                                            </li>
                                            <li id="menu-item-4676" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4676">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/cirurgia-odontologica-mooca/">Cirurgia Odontológica</a>
                                            </li>
                                            <li id="menu-item-4677" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4677">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/ortodontia/">Ortodontia</a>
                                            </li>
                                            <li id="menu-item-4678" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4678">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/diagnostico-digital-mooca/">Diagnóstico Digital</a>
                                            </li>
                                            <li id="menu-item-4679" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4679">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/dentistica-mooca/">Dentística</a>
                                            </li>
                                            <li id="menu-item-4680" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4680">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/odontopediatria-na-mooca/">Odontopediatria na Mooca</a>
                                            </li>
                                            <li id="menu-item-4687" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-4687">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/periodontia-mooca/">Periodontia</a>
                                            </li>
                                        </ul>
                                    </li>
                                    <li id="menu-item-17" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-17">
                                        <a href="https://gaspariniodontologia.com.br/agenda/">Agendamento</a>
                                    </li>
                                    <li id="menu-item-17" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-17">
                                        <a href="https://gaspariniodontologia.com.br/dicas-para-um-sorriso-perfeito/">Dicas</a>
                                    </li>
                                    <li id="menu-item-24" class="menu-item menu-item-type-post_type menu-item-object-page menu-item-24">
                                        <a href="https://gaspariniodontologia.com.br/contato-dentista-na-mooca/">Contato</a>
                                    </li>
                                    <li class="super-guacamole__menu menu-item menu-item-has-children" hidden="hidden">
                                        <span class="sub-menu-toggle"></span><a href="#">Saiba mais</a>
                                        <ul class="sub-menu">
                                            <li class="super-guacamole__menu__child menu-item" hidden="hidden">
                                                <a href="https://gaspariniodontologia.com.br">Home</a>
                                            </li>
                                            <li class="super-guacamole__menu__child menu-item" hidden="hidden">
                                                <a href="https://gaspariniodontologia.com.br/clinica-odontologica-mooca/">A Clínica</a>
                                            </li>
                                            <li class="super-guacamole__menu__child menu-item" hidden="hidden">
                                                <a href="https://gaspariniodontologia.com.br/tratamentos-odontologicos-mooca/">Tratamentos</a>
                                            </li>
                                            <li class="super-guacamole__menu__child menu-item" hidden="hidden">
                                                <a href="https://gaspariniodontologia.com.br/dicas-para-um-sorriso-perfeito/">Dicas</a>
                                            </li>
                                            <li class="super-guacamole__menu__child menu-item" hidden="hidden">
                                                <a href="https://gaspariniodontologia.com.br/contato-dentista-na-mooca/">Contato</a>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </nav>
                        <div class="pseudoStickyBlock" style="position: relative; display: block; height: 0px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="breadcrumbs">
            <div class="container">
                <div class="row">
                    <div class="col breadcrumbs__title">
                        <h1 class="page-title">Agenda</h1>
                    </div>
                    <div class="col breadcrumbs__items">
                        <div class="float-right breadcrumbs__content">
                            <div class="breadcrumbs__wrap">
                                <div class="breadcrumbs__item"><a href="https://gaspariniodontologia.com.br/" class="breadcrumbs__item-link is-home" rel="home" title="Home">Home</a></div>
                                <div class="breadcrumbs__item">
                                    <div class="breadcrumbs__item-sep">|</div>
                                </div>
                                <div class="breadcrumbs__item"><span class="breadcrumbs__item-target">Agenda</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container bg-white mt-0 my-5">
        <form id="formGasparini" >
            <div class="row">
                <div class="col-12 text-center">
                    <h2 class="mt-4 mb-3" >Agende sua consulta</h2>
                </div>

                <div class="col-12 col-md-6">
                    <div class="row">
                        <div class="col-12 col-md-8 mx-auto">
                            <div class="my-3">
                                <label for="name"   class="form-label">Nome *</label>
                                <input type="text"  class="form-control" id="name"  name="name"  placeholder="Digite seu nome"   required autofocus >
                            </div>
                            <div class="mb-3">
                                <label for="email"  class="form-label">E-mail *</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Digite seu e-mail" required >
                            </div>
                            <div class="mb-3">
                                <label for="phone"  class="form-label">Telefone *</label>
                                <input type="text"  class="form-control" id="phone" name="phone" placeholder="(xx) xxxxx-xxxx"   required >
                            </div>
                            <div class="col-auto mb-3">
                                <span class="form-text">
                                    * Campos obrigatórios
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 mt-4 mt-md-0 text-center">
                    <?php
                        if ($code != 200 && $error)
                        {
                    ?>
                            <div id="div-error" class="row">
                                <div class="col-12">
                                    <h4>Ocorreu um erro!</h4>
                                    <p>Não foi possível carregar a agenda.</p>
                                    <p>Atualize a página e tente novamente.</p>
                                </div>
                            </div>
                    <?php
                        }
                        else
                        {
                    ?>
                            <div id="div-header-schedule" class="row">
                                <div class="col-12 d-flex justify-content-between mb-2">
                                    <button class="btn btn-gasparini" type="button" data-bs-target="#gaspariiniCarousel" data-bs-slide="prev">
                                        <i class="bi-chevron-left"></i>
                                    </button>
                                    <h5 class="my-2" >Horários</h5>
                                    <button class="btn btn-gasparini" type="button" data-bs-target="#gaspariiniCarousel" data-bs-slide="next">
                                        <i class="bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>

                            <div id="div-schedule" class="row">
                                <div id="gaspariiniCarousel" class="carousel slide" data-bs-interval="0" data-bs-pause="true" data-bs-wrap="true"  >
                                    <div class="carousel-inner px-1">
                                        <div class="carousel-item active" >
                                            <div class="row">
                                            <?php
                                                $i = 0;
                                                foreach ($schedules as $day => $sch)
                                                {
                                                    $i++;
                                            ?>
                                                    <div class="col-3">
                                                        <div class="row">
                                                            <div class="col-12 fw-bold">
                                                                <p class="mb-1" >
                                                                    <?= $sch["day"]; ?><br><span class="text-muted fs-12" ><?= onlyDayMonth($day); ?></span>
                                                                </p>
                                                            </div>
                                                            <?php
                                                                foreach (HOURS as $constHour)
                                                                {
                                                                    $id = getId($day, $constHour);

                                                                    if ($sch["dayWeek"] == "Sáb" ||
                                                                        $sch["dayWeek"] == "Dom" ||
                                                                        (($sch["dayWeek"] != "Seg" && $sch["dayWeek"] != "Qua") && $constHour == "17:00") ||
                                                                        ($sch["dayWeek"] == "Seg" && in_array($constHour, ["09:00", "10:00", "11:00"])))
                                                                    {
                                                                        // Não exibe texto/horario, se for sabado ou domingo
                                                                        // ou terça, quinta e sexta as 17h
                                                                        $disb  = "disabled";
                                                                        $class = "btn-outline-secondary border-white";
                                                                        $text  = "-";
                                                                    }
                                                                    else if (in_array($constHour, $sch["hours"]) )
                                                                    {
                                                                        if ($sch["day"] == "Hoje" && $constHour <= date("H:i"))
                                                                        {
                                                                            // Se for o dia corrente e o horário já tiver passado, fica bloqueado
                                                                            $disb  = "disabled";
                                                                            $class = "btn-outline-secondary text-decoration-line-through";
                                                                        }
                                                                        else
                                                                        {
                                                                            // Exibe todos os horarios disponiveis
                                                                            $disb  = "";
                                                                            $class = "btn-outline-gasparini";
                                                                        }

                                                                        $text = $constHour;
                                                                    }
                                                                    else
                                                                    {
                                                                        // Exibe os indisponiveis
                                                                        $disb  = "disabled";
                                                                        $class = "btn-outline-secondary text-decoration-line-through";
                                                                        $text  = $constHour;
                                                                    }

                                                                    echo "
                                                                    <div class=\"col-12 my-1\">
                                                                        <input class=\"btn-check\" id=\"$id\" type=\"radio\" name=\"hour\" value=\"$id\" autocomplete=\"off\" " . $disb . " >
                                                                        <label class=\"btn " . $class . "\" for=\"$id\">" . $text . "</label>
                                                                    </div>
                                                                    ";
                                                                }
                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php
                                                    if ($i % 4 == 0 && $i < 16)
                                                    {
                                                ?>
                                            </div>
                                        </div>
                                        <div class="carousel-item" >
                                            <div class="row">
                                            <?php
                                                    }
                                                }
                                            ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="my-3">
                                    <span id="error_hour" class="h6 text-danger d-none">
                                        Selecione um horário
                                    </span>
                                </div>
                            </div>
                    <?php
                        }
                    ?>

                </div>

                <div class="col-12 mb-4 text-center">
                    <div class="col-auto mb-3">
                        <span class="form-text">
                            Agora é só confirmar e marcar sua consulta.
                        </span>
                    </div>
                    <button id="btnSubmit" type="submit" class="btn btn-gasparini btn-lg" disabled >Marcar</button>
                </div>
            </div>
        </form>
    </div>

    <div id="modalEnd" class="modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered "> <!-- modal-fullscreen-sm-down -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Marcando sua consulta...</h5>
                </div>
                <div class="modal-body">
                    <div class="mx-auto my-5 spin-load"></div>
                </div>
                <div class="modal-footer text-center d-none">
                    <button type="button" class="btn btn-secondary" >Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <footer id="colophon" class="container-fluid text-center px-0 site-footer" >
        <div class="footer-area-wrap invert">
            <div class="container">
                <section id="footer-area" class="footer-area widget-area row">
                    <aside class="col-12 mt-2 mb-3">
                        <h6 class="open-sans text-uppercase mb-3">Gasparini Odontologia</h6>
                        <div class="">
                            <a class="text-decoration-none" href="https://gaspariniodontologia.com.br/">
                                <img class="lazy-loaded" src="https://gaspariniodontologia.com.br/wp-content/uploads/2019/02/logo_gasparini_quality_high_cantos_arredondados.png" data-lazy-type="image" data-src="https://gaspariniodontologia.com.br/wp-content/uploads/2019/02/logo_gasparini_quality_high_cantos_arredondados.png" alt="Gasparini Odontologia | Dentista na Mooca | Clínica Odontológica">
                                <noscript>
                                    <img class="" src="https://gaspariniodontologia.com.br/wp-content/uploads/2019/02/logo_gasparini_quality_high_cantos_arredondados.png" alt="Gasparini Odontologia | Dentista na Mooca | Clínica Odontológica">
                                </noscript>
                            </a>
                        </div>
                        <div class="my-0 py-0">
                            <br>
                            <p>Dentistas na Mooca com tradição de mais de 25 anos e o mais alto padrão em tratamentos odontológicos!</p>
                            <br>
                            <h6 class="open-sans text-uppercase" >Clínica Odontológica <br> na Mooca</h6>
                            <br>
                            <a class="text-decoration-none" href="https://gaspariniodontologia.com.br/contato-dentista-na-mooca/">
                                AV. PAES DE BARROS, 2402 <br>
                                PARQUE DA MOOCA, SP <br>
                                Tel.: (11) 2272-5449 <br>
                            </a>
                            <a class="text-decoration-none" href="http://api.whatsapp.com/send?1=pt_BR&amp;phone=5511977556501" target="_blank" rel="noopener">
                                WhatsApp: 11 97755-6501
                            </a>
                        </div>
                    </aside>
                </section>
            </div>
        </div>
        <div class="footer-container">
            <div>
                <div class="footer-copyright ">2021 <a href="https://gaspariniodontologia.com.br/" class="text-decoration-none"> ©  Gasparini Odontologia</a> | <a href="https://agenciasi.com.br/" class="text-decoration-none"> Agência SI - Inbound Marketing</a></div>
                <nav id="footer-navigation" class="footer-menu" role="navigation"></nav>
            </div>
        </div>
        <!-- <a href="#" id="toTop" class="btn text-decoration-none" style="display: inline;">
            <i class="bi-chevron-up"></i>
        </a> -->
    </footer>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"  integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"                         crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"             integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js" integrity="sha512-pHVGpX7F/27yZ0ISY+VVjyULApbDlD0/X0rgGbTqCE7WFW5MezNTWG/dnhtbBuICzsd0WQPgpE4REBLv+UqChw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        $(document).ready( function() {
            var SPMaskBehavior = function (val) {
                return val.replace(/\D/g, "").length === 11 ? "(00) 00000-0000" : "(00) 0000-00009";
            }, spOptions = { onKeyPress: function(val, e, field, options) { field.mask(SPMaskBehavior.apply({}, arguments), options); } };
            $("#phone").mask(SPMaskBehavior, spOptions);

            $("input[name=hour]").change( function() {
                $("#btnSubmit").prop("disabled", false);
            });

            let myModal = new bootstrap.Modal(document.getElementById("modalEnd"), { keyboard: false, backdrop: "static" });

            $("#formGasparini").on("submit", function(e) {
                e.preventDefault();

                myModal.show();

                $("#error_hour").addClass("d-none");

                const name   = $("#name").val();
                const email  = $("#email").val();
                const phone  = $("#phone").val();

                let datetime = $("input[name=hour]:checked", "#formGasparini").val();
                if (!datetime)
                {
                    $("#error_hour").removeClass("d-none");
                    myModal.hide();
                    return;
                }

                datetime   = datetime.split("_");

                const date = `${datetime[2]}-${datetime[1]}-${datetime[0]}`;
                const hour = `${datetime[3]}:00`;

                let title  = "";
                let text   = "";
                let type   = "";

                $.ajax({
                    type: "POST",
                    url: "exec.php",
                    data: {
                        "dt_data"  : date,
                        "shorario" : hour,
                        "snome"    : name,
                        "sfone1"   : phone,
                        "smotivo"  : email
                    },
                    success: function ( data ) {
                        data = JSON.parse( data );

                        if (data["code"] == 200 && data["success"])
                        {
                            type  = "text-success";
                            title = "Consulta marcada!";
                            text  = `
                                <h5>Muito bem!</h5>
                                <p><b>${name.split(" ")[0]}</b>, sua consulta foi marcada para o dia <b>${datetime[0]}/${datetime[1]}/${datetime[2]}</b> às <b>${datetime[3]}h</b>.</p>
                                <p class="mb-0" >A confirmação será pelo e-mail (<b>${email}</b>) ou pelo telefone (<b>${phone}</b>).</p>
                            `;
                        }
                        else
                        {
                            console.log( JSON.parse( data ) );

                            type  = "text-danger";
                            title = "Algo de errado!";
                            text  = "<h5>Ocorreu um erro!</h5><p>Sua consulta não pode ser registrada.</p><p class='mb-0' >Atualize a página e tente novamente.</p>";
                        }
                    },
                    error: function ( err ) {
                        console.log( JSON.parse( err ) );

                        type  = "text-danger";
                        title = "Algo de errado!";
                        text  = "<h5>Ocorreu um erro!</h5><p>Sua consulta não pode ser registrada.</p><p class='mb-0' >Atualize a página e tente novamente.</p>";
                    }
                });

                setTimeout( function () {
                    $("#modalEnd .modal-footer").removeClass("d-none");
                    $("#modalEnd .modal-header h5").addClass(type);
                    $("#modalEnd .modal-title").html(title);
                    $("#modalEnd .modal-body").html(text);
                }, 2000);
            });

            $("#modalEnd button").on("click", function(e) {
                myModal.hide();
                window.location.reload();
            });
        });




        var menu = $("#site-navigation");
        $(".menu-toggle").on("click", function (e) {
            var toggled = menu.hasClass("toggled");

            if (toggled)
                menu.removeClass("toggled");
            else
                menu.addClass("toggled");
        });

        var submenu = $("#menu-item-4669");
        $("#menu-item-4669 .sub-menu-toggle").on("click", function (e) {
            var open = submenu.hasClass("sub-menu-open");
            var active = $(".sub-menu-toggle", submenu).hasClass("active");

            if (open && active)
            {
                submenu.removeClass("sub-menu-open");
                $(".sub-menu-toggle", submenu).removeClass("active");
            }
            else
            {
                submenu.addClass("sub-menu-open");
                $(".sub-menu-toggle", submenu).addClass("active");
            }
        });

        $(document).mouseup( function(e) {
            // if the target of the click isn't the container nor a descendant of the container
            if (!menu.is(e.target) && menu.has(e.target).length === 0)
            {
                menu.removeClass("toggled");
                submenu.removeClass("sub-menu-open");
                $(".sub-menu-toggle", submenu).removeClass("active");
            }
        });
    </script>
</body>
</html>
