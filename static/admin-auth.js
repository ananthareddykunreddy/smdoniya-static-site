(function () {
  const API_BASE = "/api/admin";
  const TOKEN_KEY = "sm_admin_token";

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
      throw new Error(data.error || "Request failed");
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

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function formatDate(value) {
    if (!value) return "-";
    const date = new Date(value.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString();
  }

  function renderFileLinks(files) {
    if (!Array.isArray(files) || files.length === 0) return "No files";
    return files
      .map((file) => {
        const name = escapeHtml(file.original || file.stored || "file");
        const path = String(file.path || "");
        const href = path.startsWith("http") ? path : `/${path.replace(/^\/+/, "")}`;
        return `<a href="${escapeHtml(href)}" target="_blank" rel="noreferrer">${name}</a>`;
      })
      .join(" | ");
  }

  function renderRequestCards(items, type) {
    if (!Array.isArray(items) || items.length === 0) {
      return '<p>No records found.</p>';
    }

    return items
      .map((item) => {
        const base = [
          `<p><strong>ID:</strong> ${escapeHtml(item.id)}</p>`,
          `<p><strong>Name:</strong> ${escapeHtml(item.full_name)}</p>`,
          `<p><strong>Email:</strong> ${escapeHtml(item.email)}</p>`,
          `<p><strong>Phone:</strong> ${escapeHtml(item.phone)}</p>`,
          `<p><strong>Created:</strong> ${escapeHtml(formatDate(item.created_at))}</p>`,
          `<p><strong>Files:</strong> ${renderFileLinks(item.uploaded_files)}</p>`,
        ];

        if (type === "appointments") {
          base.splice(4, 0, `<p><strong>Service:</strong> ${escapeHtml(item.service_type || "-")}</p>`);
          base.splice(5, 0, `<p><strong>City:</strong> ${escapeHtml(item.city || "-")}</p>`);
          if (item.notes) {
            base.push(`<p><strong>Notes:</strong> ${escapeHtml(item.notes)}</p>`);
          }
        }

        if (item.message) {
          base.push(`<p><strong>Message:</strong> ${escapeHtml(item.message)}</p>`);
        }

        return `<article class="card">${base.join("")}</article>`;
      })
      .join("");
  }

  async function handleAdminLoginSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const formData = new FormData(form);
    setStatus("admin-login-status", "Signing in...", true);
    try {
      const result = await request("/login.php", {
        method: "POST",
        body: {
          email: String(formData.get("email") || "").trim(),
          password: String(formData.get("password") || ""),
        },
      });
      localStorage.setItem(TOKEN_KEY, result.token);
      setStatus("admin-login-status", "Login successful.", true);
      window.location.href = "/admin-dashboard.html";
    } catch (error) {
      setStatus("admin-login-status", error.message || "Login failed.", false);
    }
  }

  async function ensureAdminSession() {
    const token = localStorage.getItem(TOKEN_KEY);
    if (!token) {
      window.location.href = "/admin-login.html";
      return null;
    }

    try {
      const result = await request("/me.php");
      const welcome = document.getElementById("admin-welcome");
      if (welcome && result.admin) {
        welcome.textContent = `Signed in as ${result.admin.full_name} (${result.admin.email})`;
      }
      return result.admin;
    } catch (_) {
      localStorage.removeItem(TOKEN_KEY);
      window.location.href = "/admin-login.html";
      return null;
    }
  }

  async function loadRequests() {
    setStatus("admin-dashboard-status", "Loading requests...", true);
    try {
      const result = await request("/requests.php?limit=200");

      const appointmentsList = document.getElementById("appointments-list");
      const contactsList = document.getElementById("contacts-list");
      if (appointmentsList) {
        appointmentsList.innerHTML = renderRequestCards(result.appointments || [], "appointments");
      }
      if (contactsList) {
        contactsList.innerHTML = renderRequestCards(result.contacts || [], "contacts");
      }

      setStatus(
        "admin-dashboard-status",
        `Loaded ${Array.isArray(result.appointments) ? result.appointments.length : 0} appointments and ${Array.isArray(result.contacts) ? result.contacts.length : 0} contacts.`,
        true
      );
    } catch (error) {
      setStatus("admin-dashboard-status", error.message || "Unable to load requests.", false);
    }
  }

  async function logoutAdmin() {
    try {
      await request("/logout.php", { method: "POST", body: {} });
    } catch (_) {
      // clear local token even if backend logout fails
    }
    localStorage.removeItem(TOKEN_KEY);
    window.location.href = "/admin-login.html";
  }

  const loginForm = document.getElementById("admin-login-form");
  if (loginForm) {
    loginForm.addEventListener("submit", handleAdminLoginSubmit);
  }

  const dashboard = document.getElementById("appointments-list");
  if (dashboard) {
    ensureAdminSession().then((admin) => {
      if (!admin) return;
      loadRequests();
    });

    const refreshBtn = document.getElementById("refresh-requests");
    if (refreshBtn) {
      refreshBtn.addEventListener("click", loadRequests);
    }

    const logoutBtn = document.getElementById("admin-logout");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", logoutAdmin);
    }
  }
})();
