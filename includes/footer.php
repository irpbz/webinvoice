<?php
// /includes/footer.php
// Common HTML footer, JavaScript includes for UI interactions.
// The friendly-urls.js is no longer needed as URLs are generated server-side.

// $app_base_path is available from header.php if needed for script paths,
// or can be recalculated here if footer is used independently in some contexts.
if (!isset($app_base_path)) {
    $app_base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($app_base_path === '.' || $app_base_path === '/') {
        $app_base_path = ''; // App is in root
    }
}
?>

    <script>
        // Initialize Lucide icons after DOM content is loaded or updated
        function initializeLucideIcons() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            } else {
                console.warn('Lucide library not found. Icons will not be rendered.');
            }
        }
        initializeLucideIcons(); // Initial call

        // User menu dropdown toggle
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenuDropdown = document.getElementById('userMenuDropdown');
        if (userMenuButton && userMenuDropdown) {
            userMenuButton.addEventListener('click', (event) => {
                event.stopPropagation(); // Prevent click from immediately closing due to document listener
                userMenuDropdown.classList.toggle('hidden');
                userMenuButton.setAttribute('aria-expanded', userMenuDropdown.classList.contains('hidden') ? 'false' : 'true');
            });
            // Close dropdown if clicked outside
            document.addEventListener('click', (event) => {
                if (userMenuDropdown && !userMenuDropdown.classList.contains('hidden')) {
                    if (!userMenuButton.contains(event.target) && !userMenuDropdown.contains(event.target)) {
                        userMenuDropdown.classList.add('hidden');
                        userMenuButton.setAttribute('aria-expanded', 'false');
                    }
                }
            });
            // Close with Escape key
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && userMenuDropdown && !userMenuDropdown.classList.contains('hidden')) {
                    userMenuDropdown.classList.add('hidden');
                    userMenuButton.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Mobile sidebar toggle
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mainSidebar = document.getElementById('mainSidebar');
        // Optional: Create an overlay div dynamically or have it in HTML
        let mainContentOverlay = document.getElementById('mainContentOverlay');
        if (!mainContentOverlay && mainSidebar) { // Create overlay if it doesn't exist
            mainContentOverlay = document.createElement('div');
            mainContentOverlay.id = 'mainContentOverlay';
            mainContentOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden print:hidden';
            document.body.appendChild(mainContentOverlay);
        }


        if (mobileMenuButton && mainSidebar && mainContentOverlay) {
            mobileMenuButton.addEventListener('click', (event) => {
                event.stopPropagation();
                mainSidebar.classList.toggle('translate-x-full'); // For RTL, hidden to the right by default
                mainSidebar.classList.toggle('translate-x-0');   // Show by moving to x-0
                mainContentOverlay.classList.toggle('hidden');
            });
            mainContentOverlay.addEventListener('click', () => {
                mainSidebar.classList.add('translate-x-full');
                mainSidebar.classList.remove('translate-x-0');
                mainContentOverlay.classList.add('hidden');
            });
             // Close with Escape key for mobile sidebar
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !mainSidebar.classList.contains('translate-x-full')) {
                    mainSidebar.classList.add('translate-x-full');
                    mainSidebar.classList.remove('translate-x-0');
                    mainContentOverlay.classList.add('hidden');
                }
            });
        } else {
            // console.warn('Mobile menu elements not found.');
        }

        // Simple confirmation for delete actions (can be enhanced)
        function confirmDelete(event, message = 'آیا از حذف این مورد مطمئن هستید؟ این عمل قابل بازگشت نیست.') {
            if (!confirm(message)) {
                event.preventDefault(); // Stop form submission or link navigation
                return false;
            }
            return true;
        }

        // Auto-dismiss alert messages after a few seconds
        document.addEventListener('DOMContentLoaded', () => { // Ensure this runs after DOM is ready
            const alertMessages = document.querySelectorAll('div[role="alert"]');
            alertMessages.forEach(alert => {
                const isErrorMessage = alert.classList.contains('border-red-500'); // Check if it's an error
                const autoDismissDelay = isErrorMessage ? 8000 : 5000; // Longer for errors

                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease, margin-top 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    alert.style.marginTop = '-'+alert.offsetHeight+'px'; // Collapse space
                    setTimeout(() => alert.remove(), 550); // Remove after transition
                }, autoDismissDelay);
            });
        });

        // Preview image before upload for input type=file
        function previewImage(event, previewElementId) {
            const input = event.target;
            const previewElement = document.getElementById(previewElementId);

            if (input.files && input.files[0] && previewElement) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewElement.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            } else if (previewElement) {
                // Optional: Revert to a default placeholder if no file is selected or selection is cancelled
                // previewElement.src = 'default-placeholder-image.png'; 
            }
        }
        
        // Re-initialize Lucide icons if content is added dynamically (e.g., by JS for invoice items)
        // This is a more generic way if you have a mutation observer or specific triggers.
        // For now, pages that add dynamic content with icons should call initializeLucideIcons() themselves.

    </script>
</body>
</html>