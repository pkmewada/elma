<?php

/*
|--------------------------------------------------------------------------
| REVMEAI REVIEW PAGE
|--------------------------------------------------------------------------
| No database
| No authenticationz
| No localStorage
|
| Expected URL:
|
| review.php?
| business=CFS%20GYM
| &city=Indore
| &category=Fitness%20%26%20Sports
| &sub_category=Gym
| &google_url=https%3A%2F%2Fg.page%2Fr%2Fxxxxx%2Freview
|--------------------------------------------------------------------------
*/

$businessName = trim($_GET["business"] ?? "Your Business");
$city         = trim($_GET["city"] ?? "");
$category     = trim($_GET["category"] ?? "");
$subCategory  = trim($_GET["sub_category"] ?? "");
$googleUrl    = trim($_GET["google_url"] ?? "");


/*
|--------------------------------------------------------------------------
| ESCAPE FUNCTION
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| LOCATION TEXT
|--------------------------------------------------------------------------
*/

$locationText = "";

if ($city !== "" && $subCategory !== "") {
    $locationText = $city . " · " . $subCategory;
} elseif ($city !== "") {
    $locationText = $city;
} elseif ($subCategory !== "") {
    $locationText = $subCategory;
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
        Share your experience | <?= e($businessName) ?>
    </title>


    <style>

        /* =========================================================
           RESET
        ========================================================= */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        html,
        body {
            width: 100%;
            min-height: 100%;
        }


        body {
            background: #f7f8fb;
            color: #111827;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }


        /* =========================================================
           PAGE
        ========================================================= */

        .review-page {
            width: 100%;
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px 15px;
        }


        /* =========================================================
           CARD
        ========================================================= */

        .review-card {
            width: 100%;
            max-width: 560px;

            background: #ffffff;

            border: 1px solid #e5e7eb;
            border-radius: 18px;

            padding: 32px;

            box-shadow:
                0 10px 35px rgba(0, 0, 0, 0.08);
        }


        /* =========================================================
           BRAND
        ========================================================= */

        .mini-brand {
            display: flex;
            align-items: center;
            justify-content: center;

            gap: 8px;

            font-size: 18px;
            font-weight: 700;

            color: #111827;

            margin-bottom: 28px;
        }


        .mini-brand span {
            width: 32px;
            height: 32px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;

            background: #2864ed;
            color: #ffffff;

            font-size: 17px;
            font-weight: 700;
        }


        /* =========================================================
           EYEBROW
        ========================================================= */

        .eyebrow {
            text-align: center;

            font-size: 13px;
            font-weight: 700;

            color: #2864ed;

            text-transform: uppercase;

            letter-spacing: 0.8px;

            margin-bottom: 10px;
        }


        /* =========================================================
           HEADING
        ========================================================= */

        .review-card h1 {
            text-align: center;

            font-size: 27px;
            line-height: 1.25;

            font-weight: 700;

            color: #111827;

            margin-bottom: 10px;
        }


        /* =========================================================
           LOCATION
        ========================================================= */

        .location {
            text-align: center;

            font-size: 14px;

            color: #6b7280;

            margin-bottom: 28px;
        }


        /* =========================================================
           GOOGLE RATING
        ========================================================= */

        .rating {
            display: flex;

            align-items: center;
            justify-content: center;

            gap: 8px;

            margin-bottom: 10px;
        }


        .rating button {
            width: 52px;
            height: 52px;

            border: 1px solid #d9dee8;

            border-radius: 12px;

            background: #ffffff;

            color: #d1d5db;

            font-size: 29px;

            cursor: pointer;

            transition:
                all .2s ease;
        }


        .rating button:hover {
            border-color: #f5b800;

            color: #f5b800;

            transform: translateY(-2px);
        }


        .rating button.active {
            background: #fff8df;

            border-color: #f5b800;

            color: #f5b800;

            box-shadow:
                0 4px 12px rgba(245, 184, 0, 0.18);
        }


        /* =========================================================
           RATING NOTE
        ========================================================= */

        .rating-note {
            text-align: center;

            font-size: 13px;

            color: #6b7280;

            margin-bottom: 25px;
        }


        #ratingValue {
            font-weight: 700;
            color: #111827;
        }


        /* =========================================================
           SUGGESTIONS
        ========================================================= */

        .suggestion-title {
            font-size: 14px;

            font-weight: 700;

            color: #111827;

            margin-bottom: 10px;
        }


        #suggestions {
            display: flex;

            flex-direction: column;

            gap: 10px;

            margin-bottom: 22px;
        }


        .empty-message {
            padding: 18px;

            background: #f8fafc;

            border: 1px dashed #d8dee8;

            border-radius: 10px;

            text-align: center;

            color: #6b7280;

            font-size: 13px;
        }


        /* =========================================================
           SUGGESTION BUTTON
        ========================================================= */

        .suggestion-button {
            width: 100%;

            border: 1px solid #e2e6ed;

            background: #ffffff;

            border-radius: 11px;

            padding: 14px 15px;

            text-align: left;

            font-size: 14px;

            line-height: 1.5;

            color: #374151;

            cursor: pointer;

            transition:
                all .2s ease;
        }


        .suggestion-button:hover {
            border-color: #2864ed;

            background: #f7f9ff;

            color: #111827;
        }


        .suggestion-button.selected {
            border-color: #2864ed;

            background: #eef4ff;

            color: #1d4ed8;

            box-shadow:
                0 3px 10px rgba(40, 100, 237, 0.10);
        }


        /* =========================================================
           REVIEW LABEL
        ========================================================= */

        .review-label {
            display: block;

            font-size: 14px;

            font-weight: 700;

            color: #111827;

            margin-bottom: 9px;
        }


        /* =========================================================
           TEXTAREA
        ========================================================= */

        #reviewText {
            width: 100%;

            min-height: 125px;

            resize: vertical;

            border: 1px solid #dfe3ea;

            border-radius: 11px;

            padding: 14px;

            outline: none;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 14px;

            line-height: 1.55;

            color: #111827;

            background: #ffffff;

            transition:
                border-color .2s ease,
                box-shadow .2s ease;
        }


        #reviewText:focus {
            border-color: #2864ed;

            box-shadow:
                0 0 0 3px rgba(40, 100, 237, 0.10);
        }


        /* =========================================================
           ACTION BUTTON
        ========================================================= */

        .primary-button {
            width: 100%;

            min-height: 50px;

            margin-top: 16px;

            border: 0;

            border-radius: 10px;

            background: #2864ed;

            color: #ffffff;

            font-size: 15px;

            font-weight: 700;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            transition:
                background .2s ease,
                transform .2s ease,
                box-shadow .2s ease;
        }


        .primary-button:hover {
            background: #1e55d0;

            transform: translateY(-1px);

            box-shadow:
                0 6px 16px rgba(40, 100, 237, 0.22);
        }


        .primary-button:disabled {
            background: #cbd5e1;

            cursor: not-allowed;

            transform: none;

            box-shadow: none;
        }


        /* =========================================================
           GOOGLE ICON
        ========================================================= */

        .google-icon {
            width: 20px;
            height: 20px;

            border-radius: 50%;

            background: #ffffff;

            color: #4285f4;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 13px;
            font-weight: 800;
        }


        /* =========================================================
           STATUS
        ========================================================= */

        .status-message {
            display: none;

            text-align: center;

            margin-top: 12px;

            padding: 10px;

            border-radius: 8px;

            font-size: 13px;

            font-weight: 600;
        }


        .status-message.show {
            display: block;
        }


        .status-message.success {
            background: #ecfdf3;

            color: #15803d;
        }


        .status-message.error {
            background: #fef2f2;

            color: #dc2626;
        }


        /* =========================================================
           PRIVACY
        ========================================================= */

        .privacy-note {
            text-align: center;

            margin-top: 15px;

            font-size: 12px;

            line-height: 1.5;

            color: #9ca3af;
        }


        /* =========================================================
           POWERED
        ========================================================= */

        .powered {
            text-align: center;

            margin-top: 20px;

            padding-top: 17px;

            border-top: 1px solid #f0f1f3;

            font-size: 11px;

            color: #9ca3af;
        }


        .powered strong {
            color: #2864ed;

            font-size: 13px;
        }


        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 600px) {

            .review-page {
                padding: 15px;
            }


            .review-card {
                padding: 24px 18px;

                border-radius: 15px;
            }


            .review-card h1 {
                font-size: 23px;
            }


            .rating {
                gap: 6px;
            }


            .rating button {
                width: 48px;
                height: 48px;

                font-size: 26px;
            }

        }


        @media (max-width: 360px) {

            .review-card {
                padding: 20px 14px;
            }


            .rating button {
                width: 44px;
                height: 44px;

                font-size: 24px;
            }

        }

    </style>

</head>


<body>


<main class="review-page">


    <section class="review-card">


        <!-- =====================================================
             BRAND
        ====================================================== -->

        <div class="mini-brand">
            <span>R</span>
            RevmeAI
        </div>


        <!-- =====================================================
             INTRO
        ====================================================== -->

        <p class="eyebrow">
            Your feedback matters
        </p>


        <h1>
            How was your experience at
            <?= e($businessName) ?>?
        </h1>


        <?php if ($locationText !== ""): ?>

            <p class="location">
                <?= e($locationText) ?>
            </p>

        <?php else: ?>

            <p class="location">
                We'd love to hear your experience
            </p>

        <?php endif; ?>


        <!-- =====================================================
             RATING
        ====================================================== -->

        <div
            class="rating"
            role="radiogroup"
            aria-label="Choose a rating"
        >

            <button
                type="button"
                data-rating="1"
                aria-label="1 star"
            >
                ★
            </button>

            <button
                type="button"
                data-rating="2"
                aria-label="2 stars"
            >
                ★
            </button>

            <button
                type="button"
                data-rating="3"
                aria-label="3 stars"
            >
                ★
            </button>

            <button
                type="button"
                data-rating="4"
                aria-label="4 stars"
            >
                ★
            </button>

            <button
                type="button"
                data-rating="5"
                aria-label="5 stars"
            >
                ★
            </button>

        </div>


        <p class="rating-note">

            <span id="ratingValue">
                No rating selected
            </span>

            · Choose 3, 4 or 5 stars

        </p>


        <!-- =====================================================
             SUGGESTIONS
        ====================================================== -->

        <div class="suggestion-title">
            Choose a review suggestion
        </div>


        <div id="suggestions">

            <div class="empty-message">
                Select 3, 4 or 5 stars to see review suggestions.
            </div>

        </div>


        <!-- =====================================================
             REVIEW
        ====================================================== -->

        <label
            class="review-label"
            for="reviewText"
        >
            Your review
        </label>


        <textarea
            id="reviewText"
            rows="4"
            placeholder="Select a suggestion or write your own review..."
        ></textarea>


        <!-- =====================================================
             COPY + GOOGLE
        ====================================================== -->

        <button
            class="primary-button"
            id="readyButton"
            type="button"
            disabled
        >

            <span class="google-icon">
                G
            </span>

            Copy review & open Google

            <span>
                →
            </span>

        </button>


        <!-- =====================================================
             STATUS
        ====================================================== -->

        <div
            id="statusMessage"
            class="status-message"
        ></div>


        <!-- =====================================================
             PRIVACY
        ====================================================== -->

        <p class="privacy-note">
            You stay in control. We never post anything automatically.
        </p>


        <!-- =====================================================
             POWERED
        ====================================================== -->

        <div class="powered">

            Powered by

            <strong>
                MQLUS
            </strong>

        </div>


    </section>

</main>


<script>

/*
|--------------------------------------------------------------------------
| BUSINESS / GOOGLE URL
|--------------------------------------------------------------------------
*/

const BUSINESS_NAME =
    <?= json_encode(
        $businessName,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    ) ?>;


const GOOGLE_REVIEW_URL =
    <?= json_encode(
        $googleUrl,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    ) ?>;


/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const ratingButtons =
    document.querySelectorAll(
        ".rating button"
    );


const ratingValue =
    document.getElementById(
        "ratingValue"
    );


const suggestionsContainer =
    document.getElementById(
        "suggestions"
    );


const reviewText =
    document.getElementById(
        "reviewText"
    );


const readyButton =
    document.getElementById(
        "readyButton"
    );


const statusMessage =
    document.getElementById(
        "statusMessage"
    );


/*
|--------------------------------------------------------------------------
| CURRENT RATING
|--------------------------------------------------------------------------
*/

let selectedRating = 0;


/*
|--------------------------------------------------------------------------
| REVIEW SUGGESTIONS
|--------------------------------------------------------------------------
|
| Simple predefined natural suggestions.
| No API.
| No database.
| No localStorage.
|--------------------------------------------------------------------------
*/

const reviewSuggestions = {

    3: [

        "I had a good experience at BUSINESS. The service was smooth and the staff was helpful.",

        "Overall, it was a good experience. The service was professional and everything went well.",

        "Good service and a pleasant experience. I would consider visiting BUSINESS again."

    ],


    4: [

        "I had a great experience at BUSINESS. The service was professional and the staff was very helpful.",

        "Really good experience overall. The service was smooth, friendly and professional.",

        "Very happy with the service at BUSINESS. Everything was handled well and I had a great experience."

    ],


    5: [

        "I had an excellent experience at BUSINESS. The service was outstanding and the staff was very helpful.",

        "Absolutely loved my experience at BUSINESS. Great service, friendly staff and a very smooth experience.",

        "Highly recommended! BUSINESS provided excellent service and made the whole experience wonderful."

    ]

};


/*
|--------------------------------------------------------------------------
| REPLACE BUSINESS NAME
|--------------------------------------------------------------------------
*/

function prepareSuggestion(text)
{
    return text.replace(
        /BUSINESS/g,
        BUSINESS_NAME
    );
}


/*
|--------------------------------------------------------------------------
| SHOW STATUS
|--------------------------------------------------------------------------
*/

function showStatus(
    message,
    type
)
{
    statusMessage.textContent =
        message;

    statusMessage.className =
        "status-message show " +
        type;
}


/*
|--------------------------------------------------------------------------
| HIDE STATUS
|--------------------------------------------------------------------------
*/

function hideStatus()
{
    statusMessage.textContent = "";

    statusMessage.className =
        "status-message";
}


/*
|--------------------------------------------------------------------------
| RATING CLICK
|--------------------------------------------------------------------------
*/

ratingButtons.forEach(
    function(button)
    {

        button.addEventListener(
            "click",
            function()
            {

                selectedRating =
                    Number(
                        this.dataset.rating
                    );


                /*
                -----------------------------------------
                RESET ACTIVE STARS
                -----------------------------------------
                */

                ratingButtons.forEach(
                    function(star)
                    {

                        const starRating =
                            Number(
                                star.dataset.rating
                            );


                        star.classList.toggle(
                            "active",
                            starRating <= selectedRating
                        );

                    }
                );


                /*
                -----------------------------------------
                RATING TEXT
                -----------------------------------------
                */

                ratingValue.textContent =
                    selectedRating +
                    (
                        selectedRating === 1
                            ? " star selected"
                            : " stars selected"
                    );


                hideStatus();


                /*
                -----------------------------------------
                ONLY 3 / 4 / 5
                -----------------------------------------
                */

                if (
                    selectedRating >= 3
                ) {

                    showSuggestions(
                        selectedRating
                    );

                } else {

                    suggestionsContainer.innerHTML = `

                        <div class="empty-message">

                            Please choose 3, 4 or 5 stars
                            to generate review suggestions.

                        </div>

                    `;


                    reviewText.value = "";

                    readyButton.disabled = true;

                }

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| SHOW SUGGESTIONS
|--------------------------------------------------------------------------
*/

function showSuggestions(
    rating
)
{

    const suggestions =
        reviewSuggestions[rating];


    suggestionsContainer.innerHTML = "";


    suggestions.forEach(
        function(text)
        {

            const finalText =
                prepareSuggestion(text);


            const button =
                document.createElement(
                    "button"
                );


            button.type = "button";

            button.className =
                "suggestion-button";


            button.textContent =
                finalText;


            button.addEventListener(
                "click",
                function()
                {

                    /*
                    ---------------------------------
                    REMOVE OLD SELECTED
                    ---------------------------------
                    */

                    document
                        .querySelectorAll(
                            ".suggestion-button"
                        )
                        .forEach(
                            function(item)
                            {
                                item.classList.remove(
                                    "selected"
                                );
                            }
                        );


                    /*
                    ---------------------------------
                    SELECT THIS
                    ---------------------------------
                    */

                    this.classList.add(
                        "selected"
                    );


                    /*
                    ---------------------------------
                    PUT INTO TEXTAREA
                    ---------------------------------
                    */

                    reviewText.value =
                        finalText;


                    readyButton.disabled =
                        false;


                    hideStatus();


                    /*
                    ---------------------------------
                    SCROLL
                    ---------------------------------
                    */

                    reviewText.scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });

                }
            );


            suggestionsContainer.appendChild(
                button
            );

        }
    );

}


/*
|--------------------------------------------------------------------------
| TEXTAREA MANUAL INPUT
|--------------------------------------------------------------------------
*/

reviewText.addEventListener(
    "input",
    function()
    {

        /*
        -----------------------------------------
        If user writes manually,
        enable button.
        -----------------------------------------
        */

        readyButton.disabled =
            this.value.trim() === "";


        /*
        -----------------------------------------
        Remove selected suggestion
        -----------------------------------------
        */

        if (
            this.value.trim() !== ""
        ) {

            document
                .querySelectorAll(
                    ".suggestion-button"
                )
                .forEach(
                    function(item)
                    {
                        item.classList.remove(
                            "selected"
                        );
                    }
                );

        }

    }
);


/*
|--------------------------------------------------------------------------
| COPY REVIEW + OPEN GOOGLE
|--------------------------------------------------------------------------
*/

readyButton.addEventListener(
    "click",
    function()
    {

        const review =
            reviewText.value.trim();


        /*
        -----------------------------------------
        CHECK REVIEW
        -----------------------------------------
        */

        if (
            review === ""
        ) {

            showStatus(
                "Please select or write a review first.",
                "error"
            );

            return;

        }


        /*
        -----------------------------------------
        CHECK GOOGLE URL
        -----------------------------------------
        */

        if (
            !GOOGLE_REVIEW_URL
        ) {

            showStatus(
                "Google Review URL is not available.",
                "error"
            );

            return;

        }


        /*
        -----------------------------------------
        OPEN GOOGLE IMMEDIATELY
        -----------------------------------------
        */

        const googleWindow =
            window.open(
                "https://www.google.com/maps/place//data=!4m3!3m2!1s0x3962fdd9bfb65fd9:0xa87ed2c10a54a1ce!12e1",
                "_blank"
            );


        /*
        -----------------------------------------
        COPY REVIEW
        -----------------------------------------
        */

        copyReview(
            review
        ).then(
            function()
            {

                if (
                    googleWindow
                ) {

                    showStatus(
                        "Review copied. Google Review page opened.",
                        "success"
                    );

                } else {

                    showStatus(
                        "Review copied. Please allow pop-ups to open Google.",
                        "success"
                    );

                }

            }
        ).catch(
            function()
            {

                /*
                ---------------------------------
                FALLBACK
                ---------------------------------
                */

                fallbackCopy(
                    review
                );

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| MODERN CLIPBOARD
|--------------------------------------------------------------------------
*/

async function copyReview(
    text
)
{

    if (
        navigator.clipboard &&
        window.isSecureContext
    ) {

        await navigator.clipboard.writeText(
            text
        );

        return;

    }


    /*
    -----------------------------------------
    FALLBACK
    -----------------------------------------
    */

    fallbackCopy(
        text
    );

}


/*
|--------------------------------------------------------------------------
| FALLBACK COPY
|--------------------------------------------------------------------------
*/

function fallbackCopy(
    text
)
{

    const textarea =
        document.createElement(
            "textarea"
        );


    textarea.value =
        text;


    textarea.style.position =
        "fixed";


    textarea.style.left =
        "-9999px";


    textarea.style.top =
        "0";


    document.body.appendChild(
        textarea
    );


    textarea.focus();

    textarea.select();


    try {

        document.execCommand(
            "copy"
        );

        showStatus(
            "Review copied. Google Review page opened.",
            "success"
        );

    } catch (error) {

        showStatus(
            "Could not copy automatically. Please copy the review manually.",
            "error"
        );

    }


    document.body.removeChild(
        textarea
    );

}

</script>


</body>

</html>