/**
 * Nav Class
 */

class Nav {
    getClassName() {return 'Nav';}
    constructor() {
        let self = this;

        // init properties
        self.options = {

        };

        self.init();
    }

    init() {
        let self = this;

        let collapseableItems = document.querySelectorAll('.c-nav__item--has-children > a');

        collapseableItems.forEach((link) => {
            let item = link.parentNode;

            if (item.classList.contains('c-nav__item--active') === false) {
                item.classList.add('c-nav__item--collapsed');
            }

            link.addEventListener('click', (e) => {
                console.log('click', item);
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
        
        if (document.querySelector('.c-nav__item--active') === null) {
            // no active page found - expand first child
            let firstItem = document.querySelector('.c-nav__item:first-of-type');
            firstItem.classList.remove('c-nav__item--collapsed');
        }
        
        
    }
}

export default Nav;
