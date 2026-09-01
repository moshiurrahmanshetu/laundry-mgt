/**
 * Laundry Management System (laundry-mgt) - Core JavaScript
 * Handles: Collapsible Sidebar, Mobile Offcanvas, LocalStorage Persistence, Tooltips
 */

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('main-wrapper');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mobileOverlay = document.getElementById('mobile-overlay');

    // =========================================================================
    // 1. Desktop Sidebar Collapsed State (with LocalStorage)
    // =========================================================================
    const isMobile = () => window.innerWidth < 992;

    const setSidebarCollapsed = (collapsed) => {
        if (!sidebar || !mainWrapper) return;

        if (collapsed) {
            sidebar.classList.add('collapsed');
            mainWrapper.classList.add('sidebar-collapsed');
            localStorage.setItem('laundrymgt_sidebar_collapsed', 'true');
        } else {
            sidebar.classList.remove('collapsed');
            mainWrapper.classList.remove('sidebar-collapsed');
            localStorage.setItem('laundrymgt_sidebar_collapsed', 'false');
        }
    };

    // Initialize state on load
    if (!isMobile()) {
        const savedState = localStorage.getItem('laundrymgt_sidebar_collapsed');
        if (savedState === 'true') {
            setSidebarCollapsed(true);
        }
    }

    // Toggle Button Click Handler
    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (isMobile()) {
                // Mobile toggle
                sidebar.classList.toggle('show-mobile');
                if (mobileOverlay) {
                    mobileOverlay.classList.toggle('show');
                }
            } else {
                // Desktop toggle
                const isCurrentlyCollapsed = sidebar.classList.contains('collapsed');
                setSidebarCollapsed(!isCurrentlyCollapsed);
            }
        });
    }

    // Close mobile drawer when clicking overlay
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', () => {
            if (sidebar) sidebar.classList.remove('show-mobile');
            mobileOverlay.classList.remove('show');
        });
    }

    // Close mobile drawer on window resize to desktop
    window.addEventListener('resize', () => {
        if (!isMobile()) {
            if (sidebar) sidebar.classList.remove('show-mobile');
            if (mobileOverlay) mobileOverlay.classList.remove('show');
            const savedState = localStorage.getItem('laundrymgt_sidebar_collapsed');
            if (savedState === 'true') {
                setSidebarCollapsed(true);
            } else {
                setSidebarCollapsed(false);
            }
        }
    });

    // =========================================================================
    // 2. Initialize Bootstrap Tooltips
    // =========================================================================
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl));
    }

    // =========================================================================
    // 3. Password Visibility Toggle
    // =========================================================================
    const passwordToggleButtons = document.querySelectorAll('.btn-toggle-password');
    passwordToggleButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = btn.querySelector('i');
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) {
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                } else {
                    input.type = 'password';
                    if (icon) {
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                }
            }
        });
    });

    // =========================================================================
    // 4. Client-side Avatar File Preview
    // =========================================================================
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreviewImg');
    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    avatarPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
