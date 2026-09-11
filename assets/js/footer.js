// =============================================
//  Amazing Fun World – Shared Footer
//  assets/js/footer.js
// =============================================

(function () {
  const FOOTER_HTML = `
  <footer class="bg-inverse-surface text-surface-container-lowest pt-section-gap-mobile md:pt-section-gap-desktop pb-6 md:pb-8">
    <div class="max-w-container-max mx-auto px-gutter grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-12">
      <!-- Brand -->
      <div class="space-y-6 lg:col-span-2">
        <a href="index.html" class="flex items-center hover:scale-105 transition-transform duration-200">
          <img alt="ThrillQuest Logo" class="h-20 w-auto"
            src="assets/images/logo.png" />
        </a>
        <p class="font-body-md opacity-80 leading-relaxed font-medium">
          Bringing magic and excitement to every family. Explore our world of
          thrills and create memories that last a lifetime.
        </p>
      </div>
      <!-- Links -->
      <div class="space-y-6 lg:col-span-2">
        <h4 class="font-headline-md text-headline-md">Useful Links</h4>
        <ul class="space-y-3 font-body-md opacity-70 font-bold">
          <li><a class="hover:text-primary-fixed transition-colors" href="index.html">Home</a></li>
          <li><a class="hover:text-primary-fixed transition-colors" href="about.html">About Us</a></li>
          <li><a class="hover:text-primary-fixed transition-colors" href="theme.html">Themes</a></li>
          <li><a class="hover:text-primary-fixed transition-colors" href="rides.html">Rides</a></li>
          <li><a class="hover:text-primary-fixed transition-colors" href="room.html">Rooms</a></li>
        </ul>
      </div>
      <!-- Contact -->
      <div class="space-y-6 lg:col-span-2">
        <h4 class="font-headline-md text-headline-md">Contact Us</h4>
        <div class="space-y-3 font-body-md opacity-70 font-medium">
          <p class="font-bold text-white">Amazing Fun World</p>
          <a href="https://www.google.com/maps?q=Amazing+Fun+World+Resort+In+Dwarka" target="_blank" class="hover:text-primary-fixed transition-colors inline-flex items-start gap-2">
            <span class="material-symbols-outlined text-sm shrink-0 mt-0.5">location_on</span>
            <span>At. Dhrashanvel, Devbhoomi Dwarka, Gujarat (India)</span>
          </a>
          <div class="flex flex-col gap-1">
            <a href="tel:+919427444848" class="hover:text-primary-fixed transition-colors inline-flex items-center gap-2"><span class="material-symbols-outlined text-sm">call</span> +91 9427444848</a>
            <a href="tel:+919016092103" class="hover:text-primary-fixed transition-colors inline-flex items-center gap-2"><span class="material-symbols-outlined text-sm">call</span> +91 9016092103</a>
          </div>
          <a href="mailto:amazingbooking46@gmail.com" class="hover:text-primary-fixed transition-colors inline-flex items-center gap-2 break-all"><span class="material-symbols-outlined text-sm">mail</span> amazingbooking46@gmail.com</a>
        </div>
      </div>
      <!-- Hours -->
      <div class="space-y-6 lg:col-span-3">
        <h4 class="font-headline-md text-headline-md">Opening Hours</h4>
        <div class="space-y-2 font-body-md opacity-70 font-bold">
          <p class="flex justify-between">
            <span>Mon - Sun:</span>
            <p>09:00 am - 06:00 pm</p>
          </p>
          <p class="pt-4 text-xs font-label-md text-secondary-fixed">
            Holiday hours may vary. Please check our mobile app for real-time updates.
          </p>
        </div>
      </div>
      <!-- Newsletter -->
      <div class="space-y-6 lg:col-span-3">
        <h4 class="font-headline-md text-headline-md">Newsletter</h4>
        <p class="font-body-md opacity-70 font-bold">Get the latest thrill updates and special offers.</p>
        <div class="flex gap-2">
          <input
            class="bg-surface-container-low/10 border-0 rounded-lg p-3 w-full text-white placeholder:text-white/40 font-medium"
            placeholder="Your email" type="email" />
          <button class="bg-primary px-4 rounded-lg">
            <span class="material-symbols-outlined">send</span>
          </button>
        </div>
      </div>
    </div>
    <!-- Bottom bar -->
    <div class="max-w-container-max mx-auto px-gutter mt-16 pt-8 border-t border-white/10 flex flex-col md:flex-row justify-between items-center gap-4 opacity-60 text-sm font-label-md font-bold">
      <p>© 2024 Amazing Fun World. All rights reserved.</p>
      <div class="flex gap-8">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
        <a href="#">Safety Guide</a>
      </div>
    </div>
  </footer>`;

  const placeholder = document.getElementById("footer-placeholder");
  if (placeholder) {
    placeholder.outerHTML = FOOTER_HTML;
  } else {
    document.body.insertAdjacentHTML("beforeend", FOOTER_HTML);
  }
})();
