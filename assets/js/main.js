// =============================================
//  Amazing Fun World – Shared Script
//  assets/js/main.js
// =============================================

document.addEventListener('DOMContentLoaded', function() {
  // 1. Dynamically inject AOS CSS if not present
  if (!document.querySelector('link[href*="aos.css"]')) {
    const aosCss = document.createElement('link');
    aosCss.rel = 'stylesheet';
    aosCss.href = 'https://unpkg.com/aos@2.3.1/dist/aos.css';
    document.head.appendChild(aosCss);
  }

  // 2. Add data-aos attributes dynamically to all requested components
  const animateElements = () => {
    // Headings: subtle fade-down
    document.querySelectorAll('h1, h2, h3').forEach((el, i) => { 
      if (!el.hasAttribute('data-aos')) {
        el.setAttribute('data-aos', 'fade-down'); 
        el.setAttribute('data-aos-delay', (i % 3) * 50);
      }
    });
    
    // Paragraphs and text: fade-up
    document.querySelectorAll('p').forEach((el, i) => { 
      if (!el.hasAttribute('data-aos') && !el.closest('nav') && !el.closest('footer')) {
        el.setAttribute('data-aos', 'fade-up'); 
        el.setAttribute('data-aos-delay', (i % 3) * 50 + 100);
      }
    });

    // Cards and Bento boxes: scale/zoom-in with stagger
    document.querySelectorAll('.bento-card, .ticket-card, .group.bg-white, .group.bg-surface-container, .bg-surface-container-lowest, .bg-surface-container-high').forEach((el, i) => {
      if (!el.hasAttribute('data-aos') && !el.closest('nav')) {
        el.setAttribute('data-aos', 'zoom-in');
        el.setAttribute('data-aos-delay', (i % 4) * 100);
      }
    });

    // Images: fade-right/slide
    document.querySelectorAll('img').forEach((el, i) => {
      if (!el.hasAttribute('data-aos') && !el.closest('nav') && !el.closest('a')) {
        el.setAttribute('data-aos', 'fade-right');
        el.setAttribute('data-aos-delay', (i % 2) * 150);
      }
    });

    // Buttons and styled links: fade-up
    document.querySelectorAll('button, a.bg-primary, a.bg-white, a.bg-action-yellow, a.bg-secondary-container, a.bg-surface-container-high').forEach((el, i) => {
      if (!el.hasAttribute('data-aos') && !el.closest('nav')) {
        el.setAttribute('data-aos', 'fade-up');
        el.setAttribute('data-aos-delay', (i % 3) * 100 + 50);
      }
    });

    // Icons: subtle scale
    document.querySelectorAll('.material-symbols-outlined').forEach((el, i) => {
      if (!el.hasAttribute('data-aos') && !el.closest('nav') && !el.closest('button')) {
        el.setAttribute('data-aos', 'zoom-in');
        el.setAttribute('data-aos-delay', (i % 4) * 50);
      }
    });

    // Forms and larger sections: general fade-up
    document.querySelectorAll('form, section > div').forEach((el, i) => {
      if (!el.hasAttribute('data-aos') && !el.closest('nav')) {
        el.setAttribute('data-aos', 'fade-up');
      }
    });
  };

  animateElements();

  // 3. Dynamically inject AOS JS and initialize
  if (!window.AOS) {
    const aosJs = document.createElement('script');
    aosJs.src = 'https://unpkg.com/aos@2.3.1/dist/aos.js';
    aosJs.onload = () => {
      AOS.init({
        duration: 800,
        easing: 'ease-out-cubic',
        once: false,
        mirror: true,
        offset: 50
      });
    };
    document.body.appendChild(aosJs);
  } else {
    AOS.init({
      duration: 800,
      easing: 'ease-out-cubic',
      once: false,
      mirror: true,
      offset: 50
    });
  }

  // 4. Counter Animation Logic
  const targetNumbers = ['09+', '07+', '200+', '50k+', '300+', '1M+', '9+', '12', '7', '100%', '₹ 4500'];
  
  // Find leaf elements containing exactly the target numbers
  document.querySelectorAll('span, div, p, h1, h2, h3, h4, h5, h6').forEach(el => {
    if (el.children.length === 0) {
      const text = el.innerText.trim();
      if (targetNumbers.includes(text)) {
        el.classList.add('count-up');
        el.setAttribute('data-target', text);
      }
    }
  });

  const runCounterAnimation = (element) => {
    const targetText = element.getAttribute('data-target');
    if (!targetText) return;
    
    // Extract prefix, number, and suffix
    const match = targetText.match(/^([^\d]*)(\d+(?:\.\d+)?)([^\d]*)$/);
    if (!match) return;

    const prefix = match[1];
    const targetNumber = parseFloat(match[2]);
    const suffix = match[3];
    
    // Handle leading zeros (e.g. '09+')
    const hasLeadingZero = match[2].startsWith('0') && match[2].length > 1 && !match[2].includes('.');
    const minLength = hasLeadingZero ? match[2].length : 0;
    
    const duration = 2000; // 2 seconds animation
    const startTime = performance.now();
    
    const updateCounter = (currentTime) => {
      const elapsedTime = currentTime - startTime;
      const progress = Math.min(elapsedTime / duration, 1);
      
      // ease-out-cubic formula
      const easeProgress = 1 - Math.pow(1 - progress, 3);
      
      const currentNumber = targetNumber * easeProgress;
      
      let formattedNumber = Number.isInteger(targetNumber) 
        ? Math.floor(currentNumber).toString() 
        : currentNumber.toFixed(1);
        
      if (hasLeadingZero) {
        formattedNumber = formattedNumber.padStart(minLength, '0');
      }
        
      element.innerText = `${prefix}${formattedNumber}${suffix}`;
      
      if (progress < 1) {
        requestAnimationFrame(updateCounter);
      } else {
        element.innerText = targetText; // Ensure exact final text
      }
    };
    
    requestAnimationFrame(updateCounter);
  };

  const counterObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        runCounterAnimation(entry.target);
        // Only run once per page load for counters to avoid jitter if scrolling back up quickly
        observer.unobserve(entry.target); 
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.count-up').forEach(el => {
    counterObserver.observe(el);
  });
});
