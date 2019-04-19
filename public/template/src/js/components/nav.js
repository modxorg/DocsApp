/**
 * Nav Class
 */

class Nav {
    getClassName() {return 'Nav';}
    constructor() {
        let self = this;
        self.collapseNavigation();
        self.menuButtonToggle();
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

        let allNav = document.querySelectorAll('.c-nav__item a');
        allNav.forEach(function(item) {
            item.addEventListener('click', (e) =>  {
                let target = item.getAttribute('href'),
                    main = document.getElementById('main');

                main.classList.add('l-main__loading');

                window.history.pushState(null, item.innerText, target);
                e.preventDefault();

                // Remove old active classes
                document.querySelectorAll('.c-nav__item--activepage').forEach(function(active) {
                    active.classList.remove('c-nav__item--activepage');
                    let p = active;
                    while (p = p.parentNode) {
                        if (p && p.classList && p.classList.contains('c-nav__item--active')) {
                            p.classList.remove('c-nav__item--active');
                        }
                    }
                });

                // Add new active classes
                item.parentNode.classList.add('c-nav__item--activepage');
                let np = item;
                while (np = np.parentNode) {
                    if (np && np.classList && np.classList.contains('c-nav__item')) {
                        np.classList.add('c-nav__item--active');
                    }
                }

                fetch(target)
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error("HTTP error, status = " + response.status);
                        }
                        return response.text();
                    })
                    .then(function(response) {
                        let footer = document.querySelector('.l-footer'),
                            responseDom = document.createRange().createContextualFragment(response),
                            responseMain = responseDom.getElementById('main'),
                            responseFooter = responseDom.querySelector('.l-footer');

                        main.innerHTML = '';
                        while (responseMain.firstChild) {
                            main.appendChild(responseMain.firstChild);
                        }
                        footer.innerHTML = '';
                        while (responseFooter.firstChild) {
                            footer.appendChild(responseFooter.firstChild);
                        }
                        window.scroll(0,0);
                    })
                    .catch(function(error) {
                        console.error(error);
                        alert(error.message);
                    })
                    .finally(function() {
                        main.classList.remove('l-main__loading');
                    });
            })
        });
    }

    menuButtonToggle() {
        // menu button toggle
        let toggleButton = document.querySelector('a.o-openmenu');
        if (toggleButton) {
            toggleButton.addEventListener('click', (e) => {
                // toggle state class
                toggleButton.classList.toggle('o-openmenu--opened');
                // after delay, toggle '#nav' in href attribute
                setTimeout(function(){
                    if (window.location.hash === '#nav') {
                        toggleButton.href = toggleButton.href.replace('#nav', '#');
                    } else {
                        toggleButton.href = toggleButton.href.replace('#', '#nav');
                    }
                }, 100);
                return true;
            });
        }
    }
}

export default Nav;
