(function () {
  const API_BASE = "/api/auth";
  const TOKEN_KEY = "sm_client_token";

  const i18n = {
    en: {
      loginTitle: "Client Login",
      registerTitle: "New Client Registration",
      accountTitle: "My Account",
      signIn: "Sign In",
      register: "Create Account",
      logout: "Logout",
      name: "Name",
      email: "Email",
      phone: "Phone",
      fullName: "Full name",
      password: "Password",
      book: "Book Appointment",
      sending: "Sending...",
      okLogin: "Login successful.",
      okRegister: "Registration successful.",
      fail: "Request failed. Please try again.",
    },
    it: {
      loginTitle: "Accesso Cliente",
      registerTitle: "Nuova Registrazione Cliente",
      accountTitle: "Il Mio Account",
      signIn: "Accedi",
      register: "Crea Account",
      logout: "Esci",
      name: "Nome",
      email: "Email",
      phone: "Telefono",
      fullName: "Nome completo",
      password: "Password",
      book: "Prenota Appuntamento",
      sending: "Invio...",
      okLogin: "Accesso completato.",
      okRegister: "Registrazione completata.",
      fail: "Operazione non riuscita. Riprova.",
    },
    fa: {
      loginTitle: "ورود مشتری",
      registerTitle: "ثبت‌نام مشتری جدید",
      accountTitle: "حساب من",
      signIn: "ورود",
      register: "ایجاد حساب",
      logout: "خروج",
      name: "نام",
      email: "ایمیل",
      phone: "تلفن",
      fullName: "نام کامل",
      password: "رمز عبور",
      book: "رزرو وقت",
      sending: "در حال ارسال...",
      okLogin: "ورود با موفقیت انجام شد.",
      okRegister: "ثبت‌نام با موفقیت انجام شد.",
      fail: "درخواست ناموفق بود. دوباره تلاش کنید.",
    },
  };

  const lang = document.documentElement.lang === "it"
    ? "it"
    : document.documentElement.lang === "fa"
      ? "fa"
      : "en";
  const t = i18n[lang];

  const escapeHtml = (value) =>
    String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  function ensureClientAreaMarkup() {
    if (document.getElementById("login-form")) {
      return;
    }
    const main = document.querySelector("main.page-shell");
    if (!main) return;

    main.innerHTML = `
      <section class="section">
        <div class="container split">
          <div class="panel">
            <p class="panel-kicker">${escapeHtml(t.loginTitle)}</p>
            <form id="login-form" class="contact-form">
              <label for="login-email">${escapeHtml(t.email)}</label>
              <input id="login-email" name="email" type="email" required>
              <label for="login-password">${escapeHtml(t.password)}</label>
              <input id="login-password" name="password" type="password" minlength="8" required>
              <div id="login-status" class="form-status" role="status" aria-live="polite"></div>
              <button class="btn" type="submit">${escapeHtml(t.signIn)}</button>
            </form>
          </div>
          <div class="panel">
            <p class="panel-kicker">${escapeHtml(t.registerTitle)}</p>
            <form id="register-form" class="contact-form">
              <label for="register-name">${escapeHtml(t.fullName)}</label>
              <input id="register-name" name="full_name" type="text" required>
              <label for="register-phone">${escapeHtml(t.phone)}</label>
              <input id="register-phone" name="phone" type="text">
              <label for="register-email">${escapeHtml(t.email)}</label>
              <input id="register-email" name="email" type="email" required>
              <label for="register-password">${escapeHtml(t.password)}</label>
              <input id="register-password" name="password" type="password" minlength="8" required>
              <input name="preferred_language" type="hidden" value="${escapeHtml(lang)}">
              <div id="register-status" class="form-status" role="status" aria-live="polite"></div>
              <button class="btn" type="submit">${escapeHtml(t.register)}</button>
            </form>
          </div>
        </div>
      </section>
      <section class="section section-alt">
        <div class="container">
          <div id="client-account-panel" class="panel" style="display:none;">
            <p class="panel-kicker">${escapeHtml(t.accountTitle)}</p>
            <p><strong>${escapeHtml(t.name)}:</strong> <span data-user-name>-</span></p>
            <p><strong>${escapeHtml(t.email)}:</strong> <span data-user-email>-</span></p>
            <p><strong>${escapeHtml(t.phone)}:</strong> <span data-user-phone>-</span></p>
            <div class="footer-cta-row">
              <a class="btn btn-small" href="appointments.html">${escapeHtml(t.book)}</a>
              <button id="logout-btn" class="btn btn-small btn-outline" type="button">${escapeHtml(t.logout)}</button>
            </div>
          </div>
        </div>
      </section>
    `;
  }

  async function request(path, options = {}) {
    const token = localStorage.getItem(TOKEN_KEY) || "";
    const headers = {
      "Content-Type": "application/json",
      ...(options.headers || {}),
    };
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
    const response = await fetch(`${API_BASE}${path}`, {
      method: options.method || "GET",
      headers,
      body: options.body ? JSON.stringify(options.body) : undefined,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.error || t.fail);
    }
    return data;
  }

  function setStatus(id, text, ok) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = text;
    el.classList.remove("is-error", "is-success");
    if (text) {
      el.classList.add(ok ? "is-success" : "is-error");
    }
  }

  function renderUser(user) {
    const panel = document.getElementById("client-account-panel");
    if (!panel) return;
    panel.style.display = "block";

    const nameEl = panel.querySelector("[data-user-name]");
    const emailEl = panel.querySelector("[data-user-email]");
    const phoneEl = panel.querySelector("[data-user-phone]");

    if (nameEl) nameEl.textContent = user.full_name || "-";
    if (emailEl) emailEl.textContent = user.email || "-";
    if (phoneEl) phoneEl.textContent = user.phone || "-";
  }

  function bindForms() {
    const loginForm = document.getElementById("login-form");
    const registerForm = document.getElementById("register-form");
    const logoutBtn = document.getElementById("logout-btn");

    if (loginForm) {
      loginForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const formData = new FormData(loginForm);
        setStatus("login-status", t.sending, true);
        try {
          const payload = {
            email: String(formData.get("email") || "").trim(),
            password: String(formData.get("password") || ""),
          };
          const result = await request("/login.php", { method: "POST", body: payload });
          localStorage.setItem(TOKEN_KEY, result.token);
          renderUser(result.user || {});
          setStatus("login-status", t.okLogin, true);
        } catch (error) {
          setStatus("login-status", error.message || t.fail, false);
        }
      });
    }

    if (registerForm) {
      registerForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const formData = new FormData(registerForm);
        setStatus("register-status", t.sending, true);
        try {
          const payload = {
            full_name: String(formData.get("full_name") || "").trim(),
            phone: String(formData.get("phone") || "").trim(),
            email: String(formData.get("email") || "").trim(),
            password: String(formData.get("password") || ""),
            preferred_language: String(formData.get("preferred_language") || lang),
          };
          const result = await request("/register.php", { method: "POST", body: payload });
          localStorage.setItem(TOKEN_KEY, result.token);
          renderUser(result.user || {});
          setStatus("register-status", t.okRegister, true);
          registerForm.reset();
        } catch (error) {
          setStatus("register-status", error.message || t.fail, false);
        }
      });
    }

    if (logoutBtn) {
      logoutBtn.addEventListener("click", async () => {
        try {
          await request("/logout.php", { method: "POST", body: {} });
        } catch (_) {
          // Session may already be invalid, still clear client token.
        }
        localStorage.removeItem(TOKEN_KEY);
        window.location.reload();
      });
    }
  }

  async function bootstrapSession() {
    const token = localStorage.getItem(TOKEN_KEY);
    if (!token) return;
    try {
      const result = await request("/me.php");
      renderUser(result.user || {});
    } catch (_) {
      localStorage.removeItem(TOKEN_KEY);
    }
  }

  ensureClientAreaMarkup();
  bindForms();
  bootstrapSession();
})();
