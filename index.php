<?php

date_default_timezone_set("America/Sao_Paulo");

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
            "dt_data_ini" => date("Y-m-d"), // "2021-07-30", //
            "dt_data_fim" => date("Y-m-d", strtotime(date("Y-m-d") . " +16 days")),
            "nprest"      => 1,
            "nunid"       => 1
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
        $date = date("Y-m-") . sprintf("%02d", date("d") + $i);
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

function getId($date, $hour)
{
    $hour = explode(":", $hour);
    return str_replace("/", "_", $date) . "_" . $hour[0];
}

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
 * @return string $date
 */
function formatDate($date)
{
    $date = explode("-", $date);
    return $date[2] . "/" . $date[1] . "/" . $date[0];
}

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

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

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
    </style>
    <title>Document</title>
</head>
<body class="bg-light" >

    <div class="container bg-white my-4">
        <form id="formGasparini" >
            <div class="row">
                <div class="col-12 text-center">
                    <h2 class="mt-4 mb-3" >Agende sua consulta</h2>
                </div>

                <div class="col-12 col-md-6">
                    <div class="row">
                        <div class="col-12 col-md-8 mx-auto">
                            <div class="my-3">
                                <label for="name" class="form-label">Nome *</label>
                                <input type="text" class="form-control" id="name" placeholder="Digite seu nome" required autofocus >
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail *</label>
                                <input type="email" class="form-control" id="email" placeholder="Digite seu e-mail" required >
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Telefone *</label>
                                <input type="phone" class="form-control" id="phone" placeholder="(11) 91234-5678" required >
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
                                    <h3>Ocorreu um erro!</h3>
                                </div>
                            </div>
                    <?php
                        }
                        else
                        {
                    ?>
                            <div class="row">
                                <div class="col-12 d-flex justify-content-between mb-2">
                                    <button class="btn btn-gasparini" type="button" data-bs-target="#gaspariiniCarousel" data-bs-slide="prev">&laquo;</button>
                                    <h5 class="my-2" >Horários</h5>
                                    <button class="btn btn-gasparini" type="button" data-bs-target="#gaspariiniCarousel" data-bs-slide="next">&raquo;</button>
                                </div>
                            </div>

                            <div id="div-schedule" class="row">
                                <div id="gaspariiniCarousel" class="carousel slide" data-bs-interval="0" data-bs-pause="true" data-bs-wrap="true"  >
                                    <div class="carousel-inner">
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

                <div class="col-12 mb-3 text-center">
                    <div class="col-auto mb-3">
                        <span class="form-text">
                            Agora é só confirmar e marcar sua consulta.
                        </span>
                    </div>
                    <button id="btnSubmit" type="submit" class="btn btn-gasparini" disabled >Marcar</button>
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
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js" integrity="sha512-pHVGpX7F/27yZ0ISY+VVjyULApbDlD0/X0rgGbTqCE7WFW5MezNTWG/dnhtbBuICzsd0WQPgpE4REBLv+UqChw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <Script>
        var SPMaskBehavior = function (val) {
            return val.replace(/\D/g, "").length === 11 ? "(00) 00000-0000" : "(00) 0000-00009";
        }, spOptions = { onKeyPress: function(val, e, field, options) { field.mask(SPMaskBehavior.apply({}, arguments), options); } };
        $("#phone").mask(SPMaskBehavior, spOptions);


        $(document).ready(function() {

            $("input[name=hour]").change( function() {
                $("#btnSubmit").prop("disabled", false);
            });

            $("#formGasparini").on("submit", function(e) {
                e.preventDefault();

                $("#error_hour").addClass("d-none");

                var name  = $("#name").val();
                var email = $("#email").val();
                var phone = $("#phone").val();
                var hour  = $("input[name=hour]:checked", "#formGasparini").val();

                if (!hour) $("#error_hour").removeClass("d-none");

                var treatHour = hour.split("_");

                // $.ajax();

                var text = `
                    <h5>Muito bem!</h5>
                    <p>Sua consulta foi marcada para o dia <b>${treatHour[0]}/${treatHour[1]}/${treatHour[2]}</b> às <b>${treatHour[3]}h</b>.</p>
                    <p>A confirmação será pela e-mail (<b>${email}</b>) ou pelo telefone (<b>${phone}</b>).</p>
                `;

                var myModal = new bootstrap.Modal(document.getElementById("modalEnd"), {
                    keyboard: false,
                    keyboard: false,
                    backdrop: "static"
                });

                myModal.show();

                setTimeout( function () {
                    $("#modalEnd .modal-header h5").addClass("text-success");
                    $("#modalEnd .modal-title").html("Consulta marcada!");
                    $("#modalEnd .modal-body").html(text);
                }, 3000);

                setTimeout( function () {
                    myModal.hide();
                    // window.location.reload();
                }, 15000);

            });

        });

    </Script>
</body>
</html>