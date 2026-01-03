/**
 * PWA Detection
 *
 * Detects if app is running in standalone mode (PWA)
 * and stores this info in a cookie for PHP to read
 */

const isStandalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone ||
    document.referrer.includes("android-app://");

if (isStandalone) {
    // Set cookie that PHP can read (1 year expiry)
    document.cookie =
        "pwa_standalone=1; path=/; max-age=31536000; SameSite=Lax";
}

window
    .matchMedia("(display-mode: standalone)")
    .addEventListener("change", ({ matches }) => {
        if (matches) {
            toast.error("Error!", "You're offline");
        } else {
            toast.success("Success!", "Back online");
        }
    });
