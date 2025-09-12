/**
 * Admin Dashboard JavaScript - Extracted from dashboard.php
 * Handles counter animations, accessibility, and performance monitoring
 */

// Performance-optimized counter animation
function animateCounter(element, target, duration = 1000) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = target % 1 === 0 ? Math.floor(current) : current.toFixed(1);
    }, 16);
}

// Animate counters when page loads
document.addEventListener('DOMContentLoaded', () => {
    const counters = document.querySelectorAll('.stat-number');
    counters.forEach(counter => {
        const target = parseFloat(counter.textContent);
        if (!isNaN(target)) {
            animateCounter(counter, target, 1500);
        }
    });
});

// Enhanced keyboard navigation
document.addEventListener('keydown', (e) => {
    // ESC key to close details elements
    if (e.key === 'Escape') {
        const openDetails = document.querySelector('details[open]');
        if (openDetails) {
            openDetails.removeAttribute('open');
        }
    }
});

// Accessibility: Announce dynamic content changes
function announceToScreenReader(message) {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.className = 'sr-only';
    announcement.textContent = message;
    document.body.appendChild(announcement);
    
    setTimeout(() => {
        document.body.removeChild(announcement);
    }, 1000);
}

// Performance monitoring
if ('performance' in window) {
    window.addEventListener('load', () => {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        if (loadTime > 2000) {
            console.warn('Page load time exceeded 2 seconds:', loadTime + 'ms');
        }
    });
}