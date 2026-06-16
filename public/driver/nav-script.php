<script>
document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('[data-menu]');
    if (!menu) {
        return;
    }

    const toggle = menu.querySelector('.floating-menu__toggle');
    const list = menu.querySelector('.floating-menu__list');
    const links = Array.from(menu.querySelectorAll('.floating-menu__link'));
    let isOpen = false;

    const setMenuState = (open) => {
        isOpen = open;
        menu.classList.toggle('floating-menu--open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        list.setAttribute('aria-hidden', open ? 'false' : 'true');
        toggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');

        if (!open) {
            toggle.focus();
        }
    };

    const openMenu = () => {
        if (isOpen) {
            return;
        }

        setMenuState(true);

        if (links.length > 0) {
            window.requestAnimationFrame(() => {
                links[0].focus();
            });
        }
    };

    const closeMenu = () => {
        if (!isOpen) {
            return;
        }

        setMenuState(false);
    };

    toggle.addEventListener('click', () => {
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    toggle.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu();
        }
    });

    links.forEach((link, index) => {
        link.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeMenu();
                return;
            }

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                event.preventDefault();
                const nextIndex = (index + 1) % links.length;
                links[nextIndex].focus();
            }

            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                event.preventDefault();
                const previousIndex = (index - 1 + links.length) % links.length;
                links[previousIndex].focus();
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
});
</script>
