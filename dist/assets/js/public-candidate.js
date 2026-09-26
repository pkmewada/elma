const appliedRole = document.getElementById("modal-appliedRole");
const portfolioWrapper = document.getElementById("portfolioUrlWrapper");
const portfolioInput = document.getElementById("modal-portfolioUrl");

const API_URL = "api/public/submitCandidate.php";

function togglePortfolioField() {
    const role = appliedRole.value.toLowerCase();
    const requiresPortfolio = role.includes("graphic") || role.includes("video");

    if (requiresPortfolio) {
        portfolioWrapper.classList.remove("d-none");
        portfolioInput.required = true;
    } else {
        portfolioWrapper.classList.add("d-none");
        portfolioInput.required = false;
        portfolioInput.value = "";
        portfolioInput.classList.remove("is-invalid");
    }
}

portfolioInput.addEventListener("blur", function() {
    if (this.value.trim() === "") return;
    try {
        new URL(this.value);
        this.classList.remove("is-invalid");
    } catch {
        this.classList.add("is-invalid");
    }
});

appliedRole.addEventListener("change", togglePortfolioField);
togglePortfolioField();

/**************************************************************
 * AJAX FORM SUBMIT
 **************************************************************/

const form = document.getElementById("addCandidateForm");
const submitBtn = document.getElementById("addCandidateSubmitBtn");
const spinner = document.getElementById("addCandidateSubmitSpinner");
const submitText = document.getElementById("addCandidateSubmitText");

form.addEventListener("submit", async function(e) {
    e.preventDefault();

    form.classList.remove("was-validated");

    if (!form.checkValidity()) {
        form.classList.add("was-validated");
        return;
    }

    // Disable submit button
    submitBtn.disabled = true;
    spinner.classList.remove("d-none");
    submitText.innerHTML = "Submitting...";

    const formData = new FormData(form);

    try {
        const response = await fetch(API_URL, {
            method: "POST",
            body: formData
        });

        const responseText = await response.text();

        console.log("HTTP Status:", response.status);
        console.log("Response:", responseText);

        let result;

        try {
            result = JSON.parse(responseText);
        } catch (e) {
            // If response is not valid JSON
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: "error",
                    title: "Server Error",
                    html: `
                        <div class="text-start">
                            <strong>Invalid response received:</strong><br>
                            <code class="text-danger">${responseText || "Empty response"}</code>
                        </div>
                    `,
                    confirmButtonColor: "#d33"
                });
            } else {
                alert("Server Error: Invalid response received.");
            }
            return;
        }

        // Check if API returned error
        if (!response.ok || result.success === false) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: "error",
                    title: "Submission Failed",
                    text: result.message || "Something went wrong. Please try again.",
                    confirmButtonColor: "#d33",
                    confirmButtonText: "Try Again"
                });
            } else {
                alert("Submission Failed: " + (result.message || "Something went wrong."));
            }
            return;
        }

        // ✅ SUCCESS
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: "success",
                title: "Application Submitted!",
                text: result.message || "Your application has been submitted successfully.",
                confirmButtonColor: "#0b8ba8",
                confirmButtonText: "OK"
            });
        } else {
            alert("Success: " + (result.message || "Application submitted successfully."));
        }

        // Reset form on success
        form.reset();
        togglePortfolioField();
        form.classList.remove("was-validated");

    } catch (error) {
        console.error("API Error:", error);

        // ❌ NETWORK ERROR
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: "error",
                title: "Network Error",
                text: error.message || "Unable to connect to server. Please check your internet connection.",
                confirmButtonColor: "#d33",
                confirmButtonText: "Try Again"
            });
        } else {
            alert("Network Error: " + (error.message || "Unable to connect to server."));
        }

    } finally {
        // Re-enable submit button
        submitBtn.disabled = false;
        spinner.classList.add("d-none");
        submitText.innerHTML = "Submit Application";
    }
});

// Optional: Check if SweetAlert2 is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swal === 'undefined') {
        console.warn('⚠️ SweetAlert2 is not loaded. Using fallback alerts.');
    } else {
        console.log('✅ SweetAlert2 is loaded successfully.');
    }
});