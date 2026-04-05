(function () {
  const API_BASE = "/api/auth";
  const TOKEN_KEY = "sm_client_token";
  const GOOGLE_CLIENT_ID = String(window.SM_GOOGLE_CLIENT_ID || "").trim();

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
      googleLabel: "Continue with Google",
      googleSuccess: "Google login successful.",
      googleNotConfigured: "Google login is not configured yet.",
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
      googleLabel: "Continua con Google",
      googleSuccess: "Accesso Google completato.",
      googleNotConfigured: "Accesso Google non ancora configurato.",
    },
    fa: {
      loginTitle: "Client Login",
      registerTitle: "Client Registration",
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
      googleLabel: "Continue with Google",
      googleSuccess: "Google login successful.",
      googleNotConfigured: "Google login is not configured yet.",
    },
  };

  const htmlLang = String(document.documentElement.lang || "").toLowerCase();
  const lang = htmlLang === "it" ? "it" : htmlLang === "fa" ? "fa" : "en";
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
      if (!document.getElementById("google-login-button")) {
        const loginForm = document.getElementById("login-form");
        if (loginForm) {
          const googleHolder = document.createElement("div");
          googleHolder.id = "google-login-button";
          googleHolder.className = "google-login-wrap";
          loginForm.appendChild(googleHolder);
        }
      }
      if (!document.getElementById("admin-login-link-inline")) {
        const loginForm = document.getElementById("login-form");
        if (loginForm) {
          const adminLink = document.createElement("p");
          adminLink.className = "google-login-note";
          adminLink.innerHTML = '<a id="admin-login-link-inline" href="/admin-login.html">Admin Login</a>';
          loginForm.appendChild(adminLink);
        }
      }
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
              <div id="google-login-button" class="google-login-wrap"></div>
              <p class="google-login-note"><a id="admin-login-link-inline" href="/admin-login.html">Admin Login</a></p>
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

  function isGoogleConfigured() {
    return GOOGLE_CLIENT_ID !== "" && GOOGLE_CLIENT_ID.indexOf("REPLACE_WITH_") !== 0;
  }

  function ensureGoogleScript() {
    return new Promise((resolve, reject) => {
      if (window.google && window.google.accounts && window.google.accounts.id) {
        resolve(true);
        return;
      }

      const existing = document.querySelector('script[data-google-identity="1"]');
      if (existing) {
        existing.addEventListener("load", () => resolve(true));
        existing.addEventListener("error", () => reject(new Error("Google script failed to load")));
        return;
      }

      const script = document.createElement("script");
      script.src = "https://accounts.google.com/gsi/client";
      script.async = true;
      script.defer = true;
      script.dataset.googleIdentity = "1";
      script.addEventListener("load", () => resolve(true));
      script.addEventListener("error", () => reject(new Error("Google script failed to load")));
      document.head.appendChild(script);
    });
  }

  async function initGoogleLogin() {
    const container = document.getElementById("google-login-button");
    if (!container) return;

    if (!isGoogleConfigured()) {
      container.innerHTML = `<p class="google-login-note">${escapeHtml(t.googleNotConfigured)}</p>`;
      return;
    }

    try {
      await ensureGoogleScript();

      window.google.accounts.id.initialize({
        client_id: GOOGLE_CLIENT_ID,
        callback: async (response) => {
          if (!response || !response.credential) {
            setStatus("login-status", t.fail, false);
            return;
          }
          setStatus("login-status", t.sending, true);
          try {
            const result = await request("/google-login.php", {
              method: "POST",
              body: {
                credential: response.credential,
                preferred_language: lang,
              },
            });
            localStorage.setItem(TOKEN_KEY, result.token);
            renderUser(result.user || {});
            setStatus("login-status", t.googleSuccess, true);
          } catch (error) {
            setStatus("login-status", error.message || t.fail, false);
          }
        },
      });

      window.google.accounts.id.renderButton(container, {
        theme: "outline",
        size: "large",
        text: "continue_with",
        shape: "pill",
        width: 280,
      });

      const label = document.createElement("p");
      label.className = "google-login-note";
      label.textContent = t.googleLabel;
      container.appendChild(label);
    } catch (_) {
      container.innerHTML = `<p class="google-login-note">${escapeHtml(t.fail)}</p>`;
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
  initGoogleLogin();
  bootstrapSession();
})();
