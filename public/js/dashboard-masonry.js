(function () {
    'use strict';

    const initializeMasonry = () => {
        const grid = document.querySelector('.dashboard-main');
        if (!grid) {
            return;
        }

        const resizeCard = (card) => {
            if (!card) {
                return;
            }

            const gridStyles = window.getComputedStyle(grid);
            const rowHeight = parseFloat(gridStyles.getPropertyValue('grid-auto-rows')) || 1;
            const rowGap = parseFloat(gridStyles.getPropertyValue('row-gap')) || 0;
            const body = card.querySelector('.card-body') || card;
            const contentHeight = body.getBoundingClientRect().height;
            const span = Math.max(1, Math.ceil((contentHeight + rowGap) / (rowHeight + rowGap)));

            card.style.gridRowEnd = `span ${span}`;
        };

        const resizeAll = () => {
            const cards = grid.querySelectorAll('.widget.card');
            if (!cards.length) {
                return;
            }

            cards.forEach((card) => resizeCard(card));
        };

        // Debounced resize for performance
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(resizeAll, 150);
        });

        // Recalculate when fonts/images change dimensions
        const images = grid.querySelectorAll('img');
        images.forEach((img) => {
            if (img.complete) {
                const card = img.closest('.widget.card');
                resizeCard(card);
            } else {
                img.addEventListener('load', () => {
                    const card = img.closest('.widget.card');
                    resizeCard(card);
                });
            }
        });

        const observer = new MutationObserver(() => resizeAll());
        observer.observe(grid, { childList: true, subtree: true });

        resizeAll();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeMasonry);
    } else {
        initializeMasonry();
    }
})();
