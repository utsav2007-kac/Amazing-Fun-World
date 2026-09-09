// =============================================
//  Amazing Fun World – Shared Navbar
//  assets/js/navbar.js
// =============================================

(function () {
  const NAVBAR_HTML = `
  <nav class="fixed top-0 w-full z-50 px-gutter py-4 transition-all duration-300" id="main-nav">
    <div class="flex justify-between items-center max-w-container-max mx-auto gap-4">
      <!-- Logo Pill -->
      <a href="index.html"
        class="flex items-center px-3 py-0 md:px-4 md:py-0 shadow-sm hover:scale-105 transition-transform duration-200 cursor-pointer">
        <img alt="ThrillQuest Logo" class="h-20 md:h-20 w-auto object-contain"
          src="assets/images/logo.png" />
      </a>

      <!-- Desktop Links Pill -->
      <div class="hidden md:flex items-center gap-6 bg-white/20 backdrop-blur-lg border border-white/20 px-8 py-3 rounded-full shadow-sm font-label-md text-xs uppercase tracking-wider">
        <a class="nav-link font-bold transition-colors" href="index.html">Home</a>
        <a class="nav-link font-bold transition-colors" href="about.html">About Us</a>
        <a class="nav-link font-bold transition-colors" href="theme.html">Themes</a>
        <a class="nav-link font-bold transition-colors" href="rides.html">Rides</a>
        <a class="nav-link font-bold transition-colors" href="room.html">Rooms</a>
        <a class="nav-link font-bold transition-colors" href="contact.html">Contact</a>
        <a class="nav-link font-bold transition-colors" href="booking.html">Booking Now</a>
      </div>

      <!-- CTA Pill -->
      <div class="flex items-center gap-2 bg-white/20 backdrop-blur-lg border border-white/20 p-1.5 rounded-full shadow-sm">
        <a href="booking.html"
          class="bg-secondary-container hover:bg-secondary text-white px-6 py-2 rounded-full font-label-md text-xs uppercase font-bold shadow-lg active:scale-95 transition-all text-center">
          Book Now
        </a>
        <!-- Hamburger -->
        <button id="mobile-menu-toggle"
          class="md:hidden flex items-center justify-center w-10 h-10 rounded-full bg-primary/10 text-primary hover:bg-primary/20 active:scale-95 transition-all focus:outline-none">
          <span class="material-symbols-outlined" id="menu-icon">menu</span>
        </button>
      </div>
    </div>

    <!-- Mobile Menu Dropdown -->
    <div id="mobile-menu"
      class="hidden md:hidden flex-col gap-3 mt-4 bg-white/95 backdrop-blur-md border border-outline-variant/30 rounded-2xl p-6 shadow-xl transition-all duration-300">
      <a class="nav-link font-bold py-2 border-b border-outline-variant/10" href="index.html">Home</a>
      <a class="nav-link font-bold py-2 border-b border-outline-variant/10" href="about.html">About Us</a>
      <a class="nav-link font-bold py-2 border-b border-outline-variant/10" href="theme.html">Themes</a>
      <a class="nav-link font-bold py-2 border-b border-outline-variant/10" href="rides.html">Rides</a>
      <a class="nav-link font-bold py-2 border-b border-outline-variant/10" href="room.html">Rooms</a>
      <a class="nav-link font-bold py-2 border-b border-outline-variant/10" href="contact.html">Contact</a>
      <a class="nav-link font-bold py-2" href="booking.html">Booking Now</a>
    </div>
  </nav>`;

  // Inject navbar
  const placeholder = document.getElementById("navbar-placeholder");
  if (placeholder) {
    placeholder.outerHTML = NAVBAR_HTML;
  } else {
    document.body.insertAdjacentHTML("afterbegin", NAVBAR_HTML);
  }

  // Highlight active page link
  const currentPage = window.location.pathname.split("/").pop() || "index.html";
  document.querySelectorAll(".nav-link").forEach((link) => {
    const href = link.getAttribute("href");
    if (href === currentPage) {
      link.classList.add("text-tertiary");
      link.classList.remove("text-on-surface-variant", "hover:text-tertiary");
    } else {
      link.classList.add("text-on-surface-variant", "hover:text-tertiary");
      link.classList.remove("text-tertiary");
    }
  });

  // Sticky nav scroll effect
  function updateNavStyle() {
    const nav = document.getElementById("main-nav");
    if (!nav) return;
    if (window.scrollY > 50) {
      nav.classList.add("shadow-lg", "py-2", "bg-white/40", "backdrop-blur-md", "border-b", "border-white/20");
      nav.classList.remove("py-4", "bg-transparent", "backdrop-blur-none", "border-b-0");
    } else {
      nav.classList.remove("shadow-lg", "py-2", "bg-white/40", "backdrop-blur-md", "border-b", "border-white/20");
      nav.classList.add("py-4", "bg-transparent", "backdrop-blur-none", "border-b-0");
    }
  }

  window.addEventListener("scroll", updateNavStyle);
  updateNavStyle();

  // Mobile menu toggle
  document.addEventListener("click", (e) => {
    const toggle = e.target.closest("#mobile-menu-toggle");
    if (!toggle) return;
    const menu = document.getElementById("mobile-menu");
    const icon = document.getElementById("menu-icon");
    if (!menu) return;
    menu.classList.toggle("hidden");
    menu.classList.toggle("flex");
    icon.textContent = menu.classList.contains("hidden") ? "menu" : "close";
  });
})();
