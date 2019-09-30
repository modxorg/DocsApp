/**
 * Nav Class
 */

class Nav {
    getClassName() {return 'Nav';}
    constructor() {
        let self = this;
        self.collapseNavigation();
        self.menuButtonToggle();
        self.searchButtonToggle();
    }

    collapseNavigation() {
        let self = this;

        // iterate items with children
        let collapseableItems = document.querySelectorAll('.c-nav__item--has-children');

        collapseableItems.forEach((item) => {
            // find link
            let link = item.querySelector(':scope > .c-nav__link');
            if (!link) {
                return;
            }

            // find chevron icon (toggle)
            let chevron = link.querySelector('.c-nav__chevron');
            if (!chevron) {
                return;
            }

            // collapse non-active items
            if (item.classList.contains('c-nav__item--active') === false) {
                item.classList.add('c-nav__item--collapsed');
            }

            // attach event listener to icon
            chevron.addEventListener('click', (e) => {
                item.classList.toggle('c-nav__item--collapsed');
                e.stopPropagation()
                e.preventDefault();
                return false;
            });
        });

        // scroll active page into view
        let activepageItem = document.querySelector('.c-nav__item--activepage');
        if (activepageItem) {
            if (activepageItem.classList.contains('c-nav__item--level2')) {
                activepageItem = activepageItem.parentNode.parentNode;
            } else {
                activepageItem = activepageItem.parentNode.parentNode.parentNode;
            }
            activepageItem.scrollIntoView();
        }
        
        // expand first item if no active item exists (e.i. root page)
        if (document.querySelector('.c-nav__item--active') === null) {
            let firstItem = document.querySelector('.c-nav__item:first-of-type');
            if (firstItem) {
                firstItem.classList.remove('c-nav__item--collapsed');
            }
        }
    }

    menuButtonToggle() {
        // menu button toggle
        let toggleButton = document.querySelector('a.o-openmenu');
        if (toggleButton) {
            toggleButton.addEventListener('click', (e) => {
                let isOpenBefore = window.location.hash === '#nav';
                toggleButton.href = toggleButton.href.replace('#nav', '#').replace('#', isOpenBefore ? '#' : '#nav');
                toggleButton.classList[isOpenBefore ? 'remove' : 'add']('o-openmenu--opened');
                return true;
            });
        }
    }

    searchButtonToggle() {
        // search button toggle
        let toggleButton = document.querySelector('a.o-search'),
            searchInput = document.getElementById('search');
        if (toggleButton) {
            toggleButton.addEventListener('click', (e) => {
                let isOpenBefore = window.location.hash === '#searchform';
                toggleButton.href = toggleButton.href.replace('#searchform', '#').replace('#', isOpenBefore ? '#' : '#searchform');
                toggleButton.classList[isOpenBefore ? 'remove' : 'add']('o-search--opened');
                if (!isOpenBefore) {
                    setTimeout(function() {
                        console.log('setting focus', searchInput);
                        searchInput.focus();
                    }, 100);
                }
                return true;
            });
        }
    }
}

export default Nav;
