// Progressive enhancement: every section stays visible if JavaScript is unavailable.
const passwordToggle = document.querySelector("[data-password-toggle]");
if (passwordToggle) {
    const passwordInput = document.getElementById(
        passwordToggle.getAttribute("aria-controls"),
    );
    passwordToggle.addEventListener("click", () => {
        const visible = passwordInput.type === "password";
        passwordInput.type = visible ? "text" : "password";
        passwordToggle.setAttribute("aria-pressed", String(visible));
        passwordToggle.setAttribute(
            "aria-label",
            visible ? "Sembunyikan kata sandi" : "Tampilkan kata sandi",
        );
    });
}
if (
    document.body.classList.contains("landing-page") &&
    "IntersectionObserver" in window &&
    !window.matchMedia("(prefers-reduced-motion: reduce)").matches
) {
    const observer = new IntersectionObserver(
        (entries, currentObserver) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("landing-visible");
                    currentObserver.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.08 },
    );
    document
        .querySelectorAll("[data-reveal]")
        .forEach((section) => observer.observe(section));

    if (window.location.hash === "#permintaan") {
        document.querySelector("#permintaan")?.scrollIntoView();
    }
}

const inquiryForm = document.querySelector("[data-inquiry-form]");
if (inquiryForm) {
    const submitButton = inquiryForm.querySelector("[data-inquiry-submit]");
    const submitLabel = submitButton.querySelector("[data-submit-label]");
    const feedback = document.querySelector(".landing-form-feedback");
    let sending = false;

    const showFeedback = (message, isError = false) => {
        feedback.textContent = message;
        feedback.hidden = false;
        feedback.className = `landing-form-feedback alert${isError ? " alert-error" : ""}`;
        feedback.focus({ preventScroll: true });
        feedback.scrollIntoView({ behavior: "smooth", block: "center" });
    };

    const clearErrors = () => {
        inquiryForm
            .querySelectorAll("[data-inquiry-error]")
            .forEach((node) => node.remove());
        inquiryForm
            .querySelectorAll('[aria-invalid="true"]')
            .forEach((field) => {
                field.removeAttribute("aria-invalid");
                field.removeAttribute("aria-describedby");
            });
    };

    inquiryForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (sending || !inquiryForm.reportValidity()) return;
        sending = true;
        submitButton.disabled = true;
        submitButton.setAttribute("aria-busy", "true");
        submitLabel.textContent = "Mengirim permintaan...";
        feedback.hidden = true;
        clearErrors();

        try {
            const response = await fetch(inquiryForm.action, {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: new FormData(inquiryForm),
                credentials: "same-origin",
            });
            const data = await response.json();

            if (response.ok) {
                inquiryForm.reset();
                showFeedback(
                    data.message ||
                        "Permintaan berhasil dikirim. Tim kami akan segera menghubungi Anda.",
                );
                return;
            }

            if (response.status === 422 && data.errors) {
                let firstInvalid = null;
                for (const [name, messages] of Object.entries(data.errors)) {
                    const field = inquiryForm.elements.namedItem(name);
                    if (!(field instanceof HTMLElement)) continue;
                    const error = document.createElement("span");
                    error.className = "error";
                    error.dataset.inquiryError = "";
                    error.id = `inquiry-error-${name}`;
                    error.textContent = messages[0];
                    field.closest(".field, .landing-form")?.append(error);
                    field.setAttribute("aria-invalid", "true");
                    field.setAttribute("aria-describedby", error.id);
                    firstInvalid ??= field;
                }
                showFeedback(
                    "Mohon periksa kembali data yang ditandai di bawah.",
                    true,
                );
                firstInvalid?.focus({ preventScroll: true });
            } else if (response.status === 429) {
                showFeedback(
                    "Terlalu banyak permintaan. Tunggu sebentar sebelum mencoba kembali.",
                    true,
                );
            } else if (response.status === 419) {
                showFeedback(
                    "Sesi formulir sudah berakhir. Muat ulang halaman ini sebelum mengirim kembali.",
                    true,
                );
            } else {
                showFeedback(
                    "Permintaan belum dapat dikirim. Silakan coba lagi beberapa saat.",
                    true,
                );
            }
        } catch {
            showFeedback(
                "Koneksi terputus atau respons tidak dapat diproses. Permintaan mungkin sudah tercatat; hubungi CS terlebih dahulu sebelum mencoba lagi agar tidak terkirim dua kali.",
                true,
            );
            submitLabel.textContent = "Periksa status dengan CS";
            submitButton.disabled = true;
            submitButton.removeAttribute("aria-busy");
            sending = false;
            return;
        } finally {
            if (submitButton.getAttribute("aria-busy") === "true") {
                sending = false;
                submitButton.disabled = false;
                submitButton.removeAttribute("aria-busy");
                submitLabel.textContent = "Kirim permintaan";
            }
        }
    });
}

const sidebar = document.querySelector("#sidebar");
const overlay = document.querySelector("[data-menu-close]");
const menuButton = document.querySelector("[data-menu-open]");
const mobileMenu = window.matchMedia("(max-width: 760px)");
const setMenu = (open, restoreFocus = false) => {
    sidebar?.classList.toggle("open", open);
    overlay?.classList.toggle("open", open);
    menuButton?.setAttribute("aria-expanded", String(open));
    if (sidebar) sidebar.inert = mobileMenu.matches && !open;
    if (open) sidebar?.querySelector("a, button")?.focus();
    if (restoreFocus) menuButton?.focus();
};
menuButton?.addEventListener("click", () => setMenu(true));
overlay?.addEventListener("click", () => setMenu(false, true));
document
    .querySelector("[data-menu-dismiss]")
    ?.addEventListener("click", () => setMenu(false, true));
document.addEventListener("keydown", (event) => {
    if (!sidebar?.classList.contains("open")) return;
    if (event.key === "Escape") setMenu(false, true);
    if (event.key === "Tab" && mobileMenu.matches) {
        const items = [...sidebar.querySelectorAll("a, button")].filter(
            (el) => el.getClientRects().length,
        );
        const first = items[0],
            last = items.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    }
});
mobileMenu.addEventListener("change", () => setMenu(false));
setMenu(false);
document.querySelectorAll("[data-stage-select]").forEach((select) => {
    const update = () =>
        document.querySelectorAll("[data-show-when]").forEach((field) => {
            field.style.display =
                field.dataset.showWhen === select.value ? "" : "none";
        });
    select.addEventListener("change", update);
    update();
});
