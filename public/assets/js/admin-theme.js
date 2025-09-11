/**
 * Gaming-Inspired Admin Theme System - jQuery Version
 * Simple theme switching for vanilla PHP + jQuery + SQLite stack
 */

$(document).ready(function() {
  // Theme management
  const THEME_KEY = 'admin-theme';
  let currentTheme = localStorage.getItem(THEME_KEY) || 'dark';
  
  // Initialize theme
  applyTheme(currentTheme);
  createThemeToggle();
  
  // Theme functions
  function applyTheme(theme) {
    $('html').attr('data-theme', theme);
    currentTheme = theme;
    localStorage.setItem(THEME_KEY, theme);
    
    // Update meta theme color for mobile
    let themeColor = theme === 'dark' ? '#0a0e1a' : '#ffffff';
    $('meta[name="theme-color"]').remove();
    $('head').append(`<meta name="theme-color" content="${themeColor}">`);
  }
  
  function createThemeToggle() {
    const userActions = $('.user-actions');
    if (userActions.length === 0) return;
    
    const themeIcon = currentTheme === 'dark' ? '🌙' : '☀️';
    const toggleHtml = `
      <div class="theme-toggle-container">
        <span class="theme-icon">${themeIcon}</span>
        <button class="theme-toggle ${currentTheme}">
        </button>
      </div>
    `;
    
    // Insert before logout button
    const logoutBtn = userActions.find('a[href*="logout"]');
    if (logoutBtn.length) {
      logoutBtn.before(toggleHtml);
    } else {
      userActions.prepend(toggleHtml);
    }
  }
  
  // Theme toggle click handler
  $(document).on('click', '.theme-toggle', function() {
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme);
    
    // Update toggle button
    $(this).removeClass('dark light').addClass(newTheme);
    $('.theme-icon').text(newTheme === 'dark' ? '🌙' : '☀️');
    
    // Play toggle effect
    $(this).addClass('clicked');
    setTimeout(() => $(this).removeClass('clicked'), 300);
  });
  
  // Keyboard shortcut (Alt + T)
  $(document).keydown(function(e) {
    if (e.altKey && e.key.toLowerCase() === 't') {
      e.preventDefault();
      $('.theme-toggle').click();
    }
  });
  
  // Add hover effects to cards
  $(document).on('mouseenter', '.admin-card', function() {
    $(this).addClass('hovered');
  });
  
  $(document).on('mouseleave', '.admin-card', function() {
    $(this).removeClass('hovered');
  });
  
  // Gaming-style button effects
  $(document).on('mouseenter', '.btn-gaming', function() {
    $(this).addClass('glow-effect');
  });
  
  $(document).on('mouseleave', '.btn-gaming', function() {
    $(this).removeClass('glow-effect');
  });
  
  // Smooth scroll for internal links
  $('a[href^="#"]').click(function(e) {
    e.preventDefault();
    const target = $($(this).attr('href'));
    if (target.length) {
      $('html, body').animate({
        scrollTop: target.offset().top - 100
      }, 500);
    }
  });
  
  // Simple loading state for forms
  $('form').submit(function() {
    $(this).find('button[type="submit"]')
      .addClass('loading')
      .prop('disabled', true);
  });
  
  // Auto-hide success/error messages
  setTimeout(function() {
    $('.success-message, .error-message').fadeOut(500);
  }, 5000);
  
  // Animate stat numbers
  function animateStatNumbers() {
    $('.stat-number').each(function() {
      const $this = $(this);
      const target = parseFloat($this.data('count')) || 0;
      const isDecimal = target % 1 !== 0;
      
      $({ count: 0 }).animate({ count: target }, {
        duration: 2000,
        easing: 'swing',
        step: function() {
          if (isDecimal) {
            $this.text(this.count.toFixed(1));
          } else {
            $this.text(Math.floor(this.count));
          }
        },
        complete: function() {
          if (isDecimal) {
            $this.text(target.toFixed(1));
          } else {
            $this.text(target);
          }
          $this.addClass('animated');
        }
      });
    });
  }
  
  // Trigger stat animation when page loads
  setTimeout(animateStatNumbers, 500);
  
  // Sidebar toggle functionality
  $(document).on('click', '.sidebar-toggle', function() {
    if ($(window).width() > 768) {
      // Desktop: mini sidebar toggle
      $('.admin-sidebar').toggleClass('collapsed');
    } else {
      // Mobile: overlay toggle
      $('.admin-sidebar').toggleClass('collapsed');
      $('.mobile-overlay').toggleClass('active');
      $('body').toggleClass('sidebar-open');
    }
  });
  
  // Mobile overlay close
  $(document).on('click', '.mobile-overlay', function() {
    $('.admin-sidebar').addClass('collapsed');
    $(this).removeClass('active');
    $('body').removeClass('sidebar-open');
  });
  
  // Auto-collapse on mobile when clicking nav link
  $(document).on('click', '.sidebar-nav a', function() {
    if ($(window).width() <= 768) {
      $('.admin-sidebar').addClass('collapsed');
      $('.mobile-overlay').removeClass('active');
      $('body').removeClass('sidebar-open');
    }
  });
  
  // Sidebar responsive behavior
  $(window).resize(function() {
    if ($(window).width() > 768) {
      // Reset mobile states when switching to desktop
      $('.mobile-overlay').removeClass('active');
      $('body').removeClass('sidebar-open');
    } else {
      // On mobile, ensure sidebar is collapsed initially
      if (!$('.admin-sidebar').hasClass('collapsed')) {
        $('.admin-sidebar').addClass('collapsed');
      }
    }
  });
  
  // Initialize mobile state
  if ($(window).width() <= 768) {
    $('.admin-sidebar').addClass('collapsed');
  }
});