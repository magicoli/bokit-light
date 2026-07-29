/**
 * The home page wallpaper: one photograph filling the window, crossfading into the next.
 *
 * The two surfaces it fades between are created here rather than written into the page: they carry
 * nothing a reader needs, and without this script there is nothing to fade.
 *
 * The two sets are two folders on disk, listed by the controller. Which one is used follows the
 * visitor's system theme and switches with it, without a reload.
 */

const ROTATION_MS = 45000;

/** How much of the scroll the photograph follows. Zero would be a fixed background, one would be a
 *  background scrolling with the page; a third of it reads as depth without drawing attention. */
const PARALLAX = 0.05;

document.addEventListener('DOMContentLoaded', () => {
    const area = document.getElementById('main-area');
    const source = document.getElementById('wallpapers');

    if (!area || !source) {
        return;
    }

    const sets = JSON.parse(source.textContent);
    const dark = window.matchMedia('(prefers-color-scheme: dark)');

    // Two surfaces, built here rather than sitting in the markup: they are a mechanism, not
    // content, and the page has nothing to say about them.
    const layers = [0, 1].map(() => {
        const layer = document.createElement('div');

        layer.className = 'wallpaper';
        layer.setAttribute('aria-hidden', 'true');
        area.prepend(layer);

        return layer;
    });

    let photographs = [];
    let next = 0;
    let front = -1;
    let timer = null;
    let failures = 0;

    /**
     * The set matching the current theme, falling back to the other one rather than to nothing.
     *
     * Shuffled, and shuffled again on every visit: the file names decide nothing, so a visitor
     * does not always land on the same photograph and the series has no first or last.
     */
    const currentSet = () => {
        const preferred = dark.matches ? sets.dark : sets.light;
        const photographs = [...(preferred.length ? preferred : dark.matches ? sets.light : sets.dark)];

        for (let i = photographs.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));

            [photographs[i], photographs[j]] = [photographs[j], photographs[i]];
        }

        return photographs;
    };

    /**
     * Load first, show second: a half-drawn photograph fading in is worse than a slower change.
     *
     * The first one of a set arrives without a fade — it replaces the gradient, and there is
     * nothing to fade from. Only the changes that follow are crossfaded.
     */
    const show = (url, instant = false) => {
        const image = new Image();

        image.onload = () => {
            failures = 0;

            const incoming = layers[(front + 1) % layers.length];

            incoming.style.backgroundImage = `url("${url}")`;

            if (instant) {
                incoming.style.transition = 'none';
            }

            incoming.classList.add('is-visible');

            if (instant) {
                // Read a layout property so the new opacity is applied before the transition comes
                // back; without it the browser would collapse both changes into one and fade.
                void incoming.offsetHeight;
                incoming.style.transition = '';
            }

            if (front >= 0) {
                layers[front].classList.remove('is-visible');
            }

            front = (front + 1) % layers.length;
        };

        image.onerror = () => {
            failures += 1;
            console.warn(`[wallpaper] could not load ${url}`);

            // A deploy renames every file, so the list this page was handed no longer exists and
            // never will again. Retrying every 45 seconds would only repeat the miss, in the
            // console and in the server's access log. Keep what is on screen until a reload.
            if (failures > 1) {
                window.clearInterval(timer);
                console.warn('[wallpaper] rotation stopped, the list is out of date — reload');
            }
        };
        image.src = url;
    };

    const start = () => {
        window.clearInterval(timer);

        photographs = currentSet();
        failures = 0;

        if (photographs.length === 0) {
            return;
        }

        show(photographs[0], true);
        next = 1;

        // One photograph is a background, not a slideshow.
        if (photographs.length > 1) {
            timer = window.setInterval(() => {
                show(photographs[next % photographs.length]);
                next += 1;
            }, ROTATION_MS);
        }
    };

    /**
     * The photograph follows the scroll, slower than the page.
     *
     * A transform and nothing else: it is composited, so the browser moves an existing layer
     * instead of repainting a full-screen image on every frame. Read inside the frame callback so
     * the value is the one the frame will be drawn with.
     */
    const parallax = () => {
        const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let pending = false;

        /**
         * How far the photograph may slide before its lower edge would come into view — read from
         * the stylesheet rather than repeated here, so changing the height of the layer is enough
         * and the two cannot drift apart.
         */
        let slack = 0;

        const measure = () => {
            const style = window.getComputedStyle(layers[0]);

            slack = Math.max(0, parseFloat(style.top) + parseFloat(style.height) - window.innerHeight);
        };

        const move = () => {
            pending = false;
            // Past the slack the photograph simply stops: a long page saturates the effect rather
            // than exposing an edge.
            const offset = Math.min(window.scrollY * PARALLAX, slack);

            layers.forEach((layer) => {
                layer.style.transform = `translate3d(0, ${-offset}px, 0)`;
            });
        };

        if (still) {
            return;
        }

        const schedule = () => {
            if (!pending) {
                pending = true;
                window.requestAnimationFrame(move);
            }
        };

        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', () => {
            measure();
            schedule();
        });

        measure();
        move();
    };

    dark.addEventListener('change', start);
    start();
    parallax();
});
