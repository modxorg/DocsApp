/**
 * Nav Class
 */

class Nav {
    getClassName() {return 'Nav';}
    constructor() {
        let self = this;
        self.init();
    }

    init() {
        let self = this;

        // iterate items with children
        let collapseableItems = document.querySelectorAll('.c-nav__item--has-children > a');
        collapseableItems.forEach((link) => {
            let item = link.parentNode;

            if (item.classList.contains('c-nav__item--active') === false) {
                item.classList.add('c-nav__item--collapsed');
            }

            // attach event listener to anchor
            link.addEventListener('click', (e) => {
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
}

export default Nav;
