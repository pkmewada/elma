<?php

/*
|--------------------------------------------------------------------------
| QR CODE / STANDEE PAGE
|--------------------------------------------------------------------------
| No database
| No authentication
| No header / sidebar / footer
|
| Business Reviews page se data:
|
| qrcode.php?business=CFS%20GYM%20103
| &city=indore
| &category=Fitness%20%26%20Sports
| &sub_category=Gym
| &google_url=https%3A%2F%2Fg.page%2Fr%2Fxxxxx%2Freview
|--------------------------------------------------------------------------
*/


$businessName = trim(
    $_GET["business"] ?? "MQLUS"
);

$city = trim(
    $_GET["city"] ?? ""
);

$category = trim(
    $_GET["category"] ?? ""
);

$subCategory = trim(
    $_GET["sub_category"] ?? ""
);

$googleUrl = trim(
    $_GET["google_url"] ?? ""
);


/*
|--------------------------------------------------------------------------
| REVIEW URL
|--------------------------------------------------------------------------
*/

if ($googleUrl !== "") {

    $reviewUrl = $googleUrl;

} else {

    $protocol =
        (
            !empty($_SERVER["HTTPS"]) &&
            $_SERVER["HTTPS"] !== "off"
        )
        ? "https"
        : "http";


    $host =
        $_SERVER["HTTP_HOST"] ?? "localhost";


    $directory =
        rtrim(
            dirname($_SERVER["SCRIPT_NAME"]),
            "/\\"
        );


    $slug =
        strtolower(
            preg_replace(
                "/[^a-zA-Z0-9]+/",
                "-",
                trim($businessName)
            )
        );


    $slug =
        trim(
            $slug,
            "-"
        );


    /*
     * Correct review.php parameter
     */
    $reviewUrl =
        $protocol .
        "://" .
        $host .
        $directory .
        "/review.php?business=" .
        rawurlencode($slug);
}


function e($value)
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= e($businessName) ?> | RevmeAI
    </title>


    <!-- QR CODE LIBRARY -->

    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js">
    </script>


    <style>

        /* =========================================================
           RESET
        ========================================================= */

        * {
            box-sizing: border-box;
        }


        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100%;
        }


        body {
            background: #ffffff;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            color: #111111;
        }


        /* =========================================================
           MAIN PAGE
        ========================================================= */

        .qr-page {

            width: 100%;
            min-height: 100vh;

            display: flex;
            flex-direction: column;

            align-items: center;
            justify-content: flex-start;

            background: #ffffff;

            overflow-x: hidden;
        }


        /* =========================================================
           POSTER
           EXACT DESKTOP SIZE
        ========================================================= */

        .qr-poster {

            width: 450px;
            height: 630px;

            margin-top: 8px;

            flex-shrink: 0;

            background: #ffffff;

            border: 4px solid #e7b51f;

            border-radius: 12px;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, 0.16);

            overflow: hidden;
        }


        /* =========================================================
           POSTER CONTENT
        ========================================================= */

        .poster-content {

            position: relative;

            width: 100%;
            height: 100%;

            padding:
                7px
                20px
                15px;

            display: flex;
            flex-direction: column;
            align-items: center;
        }


        /* =========================================================
           BUSINESS NAME
        ========================================================= */

        .poster-content h1 {

            width: 100%;

            margin: 0;

            padding: 0;

            text-align: center;

            font-family:
                Georgia,
                "Times New Roman",
                serif;

            font-size: 28px;

            line-height: 1.2;

            font-weight: 700;

            color: #111111;

            word-break: break-word;

            min-height: 34px;
        }


        /* =========================================================
           LARGE SPACE AFTER BUSINESS NAME
        ========================================================= */

        .poster-top-space {

            height: 104px;

            flex-shrink: 0;
        }


        /* =========================================================
           GOOGLE WORDMARK
        ========================================================= */

        .google-wordmark {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 61px;

            font-weight: 500;

            line-height: .95;

            letter-spacing: -5px;

            margin: 0 0 5px;

            white-space: nowrap;
        }


        .google-wordmark .g-blue {
            color: #4285f4;
        }

        .google-wordmark .o-red {
            color: #ea4335;
        }

        .google-wordmark .o-yellow {
            color: #fbbc05;
        }

        .google-wordmark .g-blue-2 {
            color: #4285f4;
        }

        .google-wordmark .l-green {
            color: #34a853;
        }

        .google-wordmark .e-red {
            color: #ea4335;
        }


        /* =========================================================
           STARS
        ========================================================= */

        .poster-stars {

            display: flex;

            align-items: center;
            justify-content: center;

            color: #e5a800;

            font-size: 37px;

            line-height: 1;

            letter-spacing: 0;

            text-shadow:
                0 2px 2px rgba(0, 0, 0, .16);

            margin-bottom: 6px;
        }


        .star-side {

            color: #e5a800;

            font-size: 24px;

            margin: 0 8px;

            opacity: .85;
        }


        /* =========================================================
           FEEDBACK TEXT
        ========================================================= */

        .poster-content h2 {

            margin: 0;

            padding: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 14px;

            line-height: 1.25;

            font-weight: 700;

            color: #111111;

            text-align: center;
        }


        .poster-subtitle {

            margin:
                1px
                0
                6px;

            padding: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 13px;

            line-height: 1.25;

            font-weight: 700;

            color: #111111;

            text-align: center;
        }


        /* =========================================================
           DIVIDER
        ========================================================= */

        .poster-divider {

            position: relative;

            width: 205px;

            height: 1px;

            background: #e4b22a;

            margin:
                3px
                0
                18px;
        }


        .poster-divider::before {

            content: "";

            position: absolute;

            left: -10px;

            top: -4px;

            width: 22px;

            height: 1px;

            background: #e4b22a;

            transform: rotate(-12deg);
        }


        .poster-divider::after {

            content: "";

            position: absolute;

            right: -10px;

            top: -4px;

            width: 22px;

            height: 1px;

            background: #e4b22a;

            transform: rotate(12deg);
        }


        .poster-divider span {

            position: absolute;

            left: 50%;

            top: -9px;

            transform:
                translateX(-50%);

            padding:
                0
                5px;

            background: #ffffff;

            color: #e1a900;

            font-size: 14px;

            line-height: 1;
        }


        /* =========================================================
           QR ROW
        ========================================================= */

        .poster-qr-row {

            width: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 6px;

            padding-left: 2px;
        }


        /* =========================================================
           SCAN COPY
        ========================================================= */

        .scan-copy {

            width: 92px;

            flex-shrink: 0;

            text-align: left;

            position: relative;

            margin-top: 8px;
        }


        .scan-copy em {

            display: block;

            color: #1467ff;

            font-family:
                "Comic Sans MS",
                "Segoe Print",
                cursive;

            font-size: 24px;

            line-height: 1;

            font-weight: 400;

            font-style: italic;

            margin-bottom: 4px;

            transform: rotate(-5deg);
        }


        .scan-copy strong {

            display: block;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 9px;

            line-height: 1.35;

            color: #111111;

            font-weight: 700;
        }


        /* Google colors inside scan text */

        .scan-copy .blue {
            color: #4285f4;
        }

        .scan-copy .red {
            color: #ea4335;
        }

        .scan-copy .yellow {
            color: #fbbc05;
        }

        .scan-copy .green {
            color: #34a853;
        }


        .scan-arrow {

            display: block;

            font-size: 30px;

            line-height: 1;

            color: #222222;

            transform:
                rotate(-12deg);

            margin-left: 48px;

            margin-top: -3px;
        }


        /* =========================================================
           QR FRAME
        ========================================================= */

        .poster-qr-frame {

            width: 174px;

            height: 174px;

            padding: 9px;

            background: #ffffff;

            border-radius: 15px;

            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;

            margin:
                15px
                17px
                15px
                10px;

            box-shadow:

                0 0 0 4px #4285f4,

                0 0 0 8px #ea4335,

                0 0 0 12px #34a853,

                0 0 0 16px #fbbc05;

            flex-shrink: 0;
        }


        #qrcode {

            width: 156px;

            height: 156px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #ffffff;

            overflow: hidden;
        }


        #qrcode img {

            width: 156px !important;

            height: 156px !important;

            display: block;
        }


        #qrcode canvas {

            width: 156px !important;

            height: 156px !important;

            display: block;
        }


        /* =========================================================
           BOTTOM COLOR WAVE
        ========================================================= */

        .poster-bottom {

            position: absolute;

            left: 0;

            bottom: 0;

            width: 100%;

            height: 105px;

            overflow: hidden;

            z-index: 1;

            pointer-events: none;
        }


        /*
         * Blue left wave
         */

        .wave-blue {

            position: absolute;

            left: -25px;

            bottom: -35px;

            width: 210px;

            height: 125px;

            background: #1769ff;

            border-radius:
                0
                80% 0
                0;

            transform:
                rotate(8deg);
        }


        /*
         * Red center-left wave
         */

        .wave-red {

            position: absolute;

            left: 48px;

            bottom: -47px;

            width: 225px;

            height: 112px;

            background: #f0442e;

            border-radius:
                65% 55%
                0 0;

            transform:
                rotate(-8deg);
        }


        /*
         * Yellow center wave
         */

        .wave-yellow {

            position: absolute;

            left: 202px;

            bottom: -48px;

            width: 180px;

            height: 105px;

            background: #f9bd0b;

            border-radius:
                65% 55%
                0 0;

            transform:
                rotate(5deg);
        }


        /*
         * Green right wave
         */

        .wave-green {

            position: absolute;

            right: -45px;

            bottom: -45px;

            width: 190px;

            height: 120px;

            background: #20a94b;

            border-radius:
                60% 0
                0 0;

            transform:
                rotate(-8deg);
        }


        /*
         * White curved separation
         */

        .wave-white {

            position: absolute;

            left: -20px;

            bottom: 60px;

            width: 510px;

            height: 70px;

            background: #ffffff;

            border-radius:
                0 0 50% 50%;

            transform:
                rotate(-1deg);
        }


        /* =========================================================
           POWERED BY
        ========================================================= */

        .poster-powered {

            position: absolute;

            z-index: 5;

            bottom: 4px;

            left: 50%;

            transform:
                translateX(-50%);

            margin: 0;

            background: #ffffff;

            border-radius: 4px;

            padding:
                4px
                9px;

            white-space: nowrap;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 9px;

            line-height: 1;

            color: #222222;

            box-shadow:
                0 1px 4px rgba(0, 0, 0, .16);
        }


        .poster-powered strong {

            font-size: 14px;

            font-style: italic;

            color: #2864ed;

            letter-spacing: -.5px;
        }


        .poster-powered strong span {
            color: #2864ed;
        }


        /* =========================================================
           ACTION AREA
        ========================================================= */

        .qr-actions {

            width: 100%;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            gap: 10px;

            padding:
                18px
                15px
                20px;

            border-top:
                1px solid #e5e7eb;

            margin-top: 8px;

            background: #ffffff;
        }


        /* =========================================================
           REVIEW URL
        ========================================================= */

        .qr-url {

            width: 100%;

            max-width: 700px;

            margin: 0;

            padding: 0 10px;

            text-align: center;

            font-size: 11px;

            line-height: 1.4;

            color: #777777;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;
        }


        /* =========================================================
           BUTTON COMMON
        ========================================================= */

        .primary-button,
        .secondary-button {

            width: 290px;

            min-height: 47px;

            border-radius: 7px;

            border: 0;

            padding:
                11px
                20px;

            font-size: 15px;

            font-weight: 600;

            cursor: pointer;

            text-decoration: none;

            text-align: center;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            transition:
                background .2s ease,
                transform .2s ease,
                box-shadow .2s ease;
        }


        /* =========================================================
           PREVIEW BUTTON
        ========================================================= */

        .primary-button {

            background: #2864ed;

            color: #ffffff;

            box-shadow:
                0 3px 8px rgba(40, 100, 237, .22);
        }


        .primary-button:hover {

            color: #ffffff;

            background: #1e55d0;

            transform:
                translateY(-1px);

            box-shadow:
                0 5px 12px rgba(40, 100, 237, .28);
        }


        /* =========================================================
           DOWNLOAD BUTTON
        ========================================================= */

        .secondary-button {

            background: #f1f3f6;

            color: #333333;
        }


        .secondary-button:hover {

            background: #e6e9ee;

            color: #222222;
        }


        /* =========================================================
           BUTTON ICON
        ========================================================= */

        .button-icon {

            font-size: 18px;

            line-height: 1;
        }


        /* =========================================================
           TABLET
        ========================================================= */

        @media (max-width: 700px) {

            .qr-poster {

                width:
                    calc(100vw - 30px);

                /*
                 * Same 450:690 ratio
                 */
                height:
                    calc((100vw - 30px) * 1.533333);

                max-height:
                    calc(100vh - 20px);

                margin-top: 8px;
            }


            .poster-content {

                padding:
                    20px
                    15px
                    12px;
            }


            .poster-content h1 {

                font-size:
                    clamp(21px, 6vw, 27px);
            }


            .poster-top-space {

                height:
                    clamp(
                        70px,
                        22vw,
                        104px
                    );
            }


            .google-wordmark {

                font-size:
                    clamp(
                        48px,
                        13.5vw,
                        61px
                    );
            }


            .poster-stars {

                font-size:
                    clamp(
                        27px,
                        8vw,
                        37px
                    );
            }


            .poster-divider {

                width:
                    48vw;

                max-width:
                    205px;
            }


            .scan-copy {

                width:
                    clamp(
                        65px,
                        20vw,
                        92px
                    );
            }


            .scan-copy em {

                font-size:
                    clamp(
                        18px,
                        6vw,
                        24px
                    );
            }


            .scan-copy strong {

                font-size:
                    clamp(
                        7px,
                        2.1vw,
                        9px
                    );
            }


            .poster-qr-frame {

                width:
                    clamp(
                        145px,
                        39vw,
                        174px
                    );

                height:
                    clamp(
                        145px,
                        39vw,
                        174px
                    );

                padding:
                    8px;

                margin:
                    14px
                    15px
                    14px
                    8px;
            }


            #qrcode {

                width:
                    calc(
                        clamp(
                            145px,
                            39vw,
                            174px
                        ) - 18px
                    );

                height:
                    calc(
                        clamp(
                            145px,
                            39vw,
                            174px
                        ) - 18px
                    );
            }


            #qrcode img,
            #qrcode canvas {

                width:
                    calc(
                        clamp(
                            145px,
                            39vw,
                            174px
                        ) - 18px
                    ) !important;

                height:
                    calc(
                        clamp(
                            145px,
                            39vw,
                            174px
                        ) - 18px
                    ) !important;
            }


            .wave-white {

                width:
                    115vw;
            }


            .wave-blue {

                width:
                    46vw;
            }


            .wave-red {

                left:
                    10vw;

                width:
                    49vw;
            }


            .wave-yellow {

                left:
                    44vw;

                width:
                    40vw;
            }


            .wave-green {

                width:
                    43vw;
            }


            .primary-button,
            .secondary-button {

                width:
                    min(
                        290px,
                        calc(100vw - 50px)
                    );
            }
        }


        /* =========================================================
           SMALL MOBILE
        ========================================================= */

        @media (max-width: 390px) {

            .qr-poster {

                width:
                    calc(100vw - 20px);

                height:
                    calc((100vw - 20px) * 1.533333);
            }


            .poster-content {

                padding:
                    17px
                    10px
                    10px;
            }


            .poster-top-space {

                height:
                    63px;
            }


            .google-wordmark {

                font-size:
                    45px;
            }


            .poster-stars {

                font-size:
                    25px;
            }


            .poster-content h2 {

                font-size:
                    12px;
            }


            .poster-subtitle {

                font-size:
                    11px;
            }


            .poster-divider {

                margin-bottom:
                    14px;
            }


            .poster-qr-frame {

                margin-left:
                    4px;

                margin-right:
                    8px;
            }


            .scan-copy {

                width:
                    58px;
            }


            .scan-copy em {

                font-size:
                    17px;
            }


            .scan-copy strong {

                font-size:
                    7px;
            }


            .scan-arrow {

                font-size:
                    24px;

                margin-left:
                    30px;
            }
        }


        /* =========================================================
           PRINT / DOWNLOAD
        ========================================================= */

        @media print {

            @page {

                size:
                    450px 690px;

                margin: 0;
            }


            html,
            body {

                width:
                    450px;

                height:
                    690px;

                margin: 0;

                padding: 0;

                background:
                    #ffffff !important;
            }


            .qr-page {

                width:
                    450px;

                min-height:
                    690px;

                height:
                    690px;

                margin: 0;

                padding: 0;

                display: block;

                background:
                    #ffffff !important;
            }


            .qr-poster {

                width:
                    450px;

                height:
                    690px;

                margin: 0;

                border:
                    4px solid #e7b51f;

                border-radius:
                    12px;

                box-shadow:
                    none;

                page-break-after:
                    avoid;

                break-after:
                    avoid;

                print-color-adjust:
                    exact;

                -webkit-print-color-adjust:
                    exact;
            }


            .qr-actions {

                display:
                    none !important;
            }
        }

    </style>

</head>


<body>


<main class="qr-page">


    <!-- =====================================================
         STANDEE POSTER
    ===================================================== -->

    <section class="qr-poster">


        <div class="poster-content">


            <!-- =================================================
                 BUSINESS NAME
            ================================================= -->

            <h1>

                <?= e($businessName) ?>

            </h1>


            <!-- TOP SPACE -->

            <div class="poster-top-space"></div>


            <!-- =================================================
                 GOOGLE
            ================================================= -->

            <div
                class="google-wordmark"
                aria-label="Google"
            >

                <span class="g-blue">G</span>
                <span class="o-red">o</span>
                <span class="o-yellow">o</span>
                <span class="g-blue-2">g</span>
                <span class="l-green">l</span>
                <span class="e-red">e</span>

            </div>


            <!-- =================================================
                 STARS
            ================================================= -->

            <div
                class="poster-stars"
                aria-label="Five stars"
            >

                <span class="star-side">⌁</span>

                ★ ★ ★ ★ ★

                <span class="star-side">⌁</span>

            </div>


            <!-- =================================================
                 FEEDBACK
            ================================================= -->

            <h2>

                Your Feedback Matters

            </h2>


            <p class="poster-subtitle">

                Leave Us a Review

            </p>


            <!-- =================================================
                 DIVIDER
            ================================================= -->

            <div class="poster-divider">

                <span>♥</span>

            </div>


            <!-- =================================================
                 QR ROW
            ================================================= -->

            <div class="poster-qr-row">


                <!-- SCAN COPY -->

                <div class="scan-copy">

                    <em>
                        Scan
                    </em>


                    <strong>

                        TO LEAVE
                        <br>

                        A REVIEW
                        <br>

                        ON

                        <span class="blue">G</span>
                        <span class="red">o</span>
                        <span class="yellow">o</span>
                        <span class="blue">g</span>
                        <span class="green">l</span>
                        <span class="red">e</span>

                    </strong>


                    <span class="scan-arrow">
                        ↘
                    </span>

                </div>


                <!-- QR FRAME -->

                <div class="poster-qr-frame">

                    <div
                        id="qrcode"
                        aria-label="Google Review QR Code"
                    ></div>

                </div>


            </div>


            <!-- =================================================
                 BOTTOM COLOR DESIGN
            ================================================= -->

            <div class="poster-bottom">

                <div class="wave-white"></div>

                <div class="wave-blue"></div>

                <div class="wave-red"></div>

                <div class="wave-yellow"></div>

                <div class="wave-green"></div>

            </div>


            <!-- =================================================
                 POWERED BY
            ================================================= -->

            <p class="poster-powered">

                Powered by

                <strong>
                    <span>MQLUS</span>
                </strong>

            </p>


        </div>

    </section>


    <!-- =====================================================
         BOTTOM ACTION AREA
    ===================================================== -->

    <div class="qr-actions">


      

        <!-- =================================================
             DOWNLOAD STANDEE
        ================================================= -->

        <button
            class="secondary-button"
            type="button"
            onclick="downloadStandee()"
        >

            <span class="button-icon">
                ↓
            </span>

            Download Standee

        </button>


    </div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| QR GENERATION
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const qrElement =
            document.getElementById("qrcode");


        const reviewUrl =
            <?= json_encode(
                $reviewUrl,
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE
            ) ?>;


        if (
            qrElement &&
            reviewUrl
        ) {

            new QRCode(
                qrElement,
                {

                    text:
                        reviewUrl,

                    width:
                        156,

                    height:
                        156,

                    colorDark:
                        "#111111",

                    colorLight:
                        "#ffffff",

                    correctLevel:
                        QRCode.CorrectLevel.H

                }
            );

        }

    }
);


/*
|--------------------------------------------------------------------------
| DOWNLOAD / PRINT STANDEE
|--------------------------------------------------------------------------
|
| Browser print dialog:
| Save as PDF
| or physical printer
|
|--------------------------------------------------------------------------
*/

function downloadStandee() {

    window.print();

}

</script>


</body>

</html>
