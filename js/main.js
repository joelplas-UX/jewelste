// ============================================================
// jeWelste — shared JS
// ============================================================

// --- Mobile nav burger ---
const burger = document.getElementById('nav-burger');
const navLinks = document.getElementById('nav-links');

if (burger && navLinks) {
  burger.addEventListener('click', () => {
    navLinks.classList.toggle('open');
    burger.setAttribute('aria-expanded', navLinks.classList.contains('open'));
  });

  // Close on link click
  navLinks.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => navLinks.classList.remove('open'));
  });
}

// --- Active nav link ---
const currentPath = window.location.pathname.split('/').pop() || 'index.html';
document.querySelectorAll('.nav-links a').forEach(a => {
  const href = a.getAttribute('href').split('/').pop();
  if (href === currentPath) a.classList.add('active');
});

// --- Stats counter animation ---
function animateCounter(el) {
  const target = parseInt(el.dataset.target, 10);
  const duration = 2000;
  const step = target / (duration / 16);
  let current = 0;

  const timer = setInterval(() => {
    current += step;
    if (current >= target) {
      current = target;
      clearInterval(timer);
    }
    el.textContent = Math.round(current).toLocaleString('nl-NL');
  }, 16);
}

const counters = document.querySelectorAll('[data-target]');
if (counters.length) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && !entry.target.dataset.animated) {
        entry.target.dataset.animated = 'true';
        animateCounter(entry.target);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(c => observer.observe(c));
}

// --- Netlify form success handling ---
const params = new URLSearchParams(window.location.search);
if (params.get('form-success') === 'true') {
  const msg = document.getElementById('form-success-msg');
  if (msg) {
    msg.style.display = 'block';
    msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}
