/**
 * Search Class
 */

class Search {
    getClassName() {return 'Search';}
    constructor() {
        let self = this;
        self.initListener();
    }

    initListener() {
        const input = document.getElementById('search'),
            searchUrl = input.form.getAttribute('action') + '?live=1',
            self = this,
            targetDom = document.querySelector('.l-live-search__container');

        input.addEventListener('input', this.debounce(function (e) {
            let v = input.value;
            if (v.length > 0) {
                targetDom.classList.add('l-live-search__container--loading');
                self.doRequest(searchUrl + '&q=' + encodeURIComponent(v));
            }
            else {
                targetDom.classList.remove('l-live-search__container--visible');
            }
        }, 150));

        input.addEventListener('keydown', (e) => {
            const KEY_TAB = 9, KEY_ESC = 27, KEY_DOWN = 40, KEY_UP = 38, KEY_ENTER = 13;

            let current = targetDom.querySelector('.l-live-search__result--selected');

            switch(e.keyCode) {
                case KEY_TAB:
                case KEY_ESC:
                    targetDom.classList.remove('l-live-search__container--visible');
                    break;

                case KEY_DOWN:
                case KEY_UP:
                    let results = targetDom.querySelectorAll('.l-live-search__result'),
                        next = results.item(0);

                    if (current) {
                        current.classList.remove('l-live-search__result--selected');
                        if (e.keyCode === KEY_DOWN && current.nextElementSibling) {
                            next = current.nextElementSibling;
                        }
                        else if (e.keyCode === KEY_UP && current.previousElementSibling) {
                            next = current.previousElementSibling;
                        }
                    }

                    if (next) {
                        next.classList.add('l-live-search__result--selected');
                    }
                    break;

                case KEY_ENTER:
                    if (current) {
                        e.preventDefault();

                        let link = current.querySelector('.c-live-search__result-link'),
                            url = link ? link.getAttribute('href') : '';

                        if (url) {
                            e.preventDefault();
                            window.location = url;
                            return false;
                        }
                    }
                    break;

                default:
                    break;
            }
        });

        document.addEventListener('click', (e) => {
            let inSearch = e.target.closest('.l-header__search');
            if (!inSearch) {
                targetDom.classList.remove('l-live-search__container--visible');
            }
        });

        input.addEventListener('focus', (e) => {
            if (input.value !== '') {
                targetDom.classList.add('l-live-search__container--visible');
            }
        });

        input.addEventListener('blur', (e) => {
            if (input.value !== '') {
                targetDom.classList.add('l-live-search__container--visible');
            }
        });
    }

    doRequest(url) {
        let targetDom = document.querySelector('.l-live-search__container');
        targetDom.classList.add('l-live-search__container--visible');
        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error("HTTP error, status = " + response.status);
                }
                return response.text();
            })
            .then(function(response) {
                let responseDom = document.createRange().createContextualFragment(response);

                targetDom.innerHTML = '';
                while (responseDom.firstChild) {
                    targetDom.appendChild(responseDom.firstChild);
                }
            })
            .catch(function(error) {
                console.error(error);
            })
            .finally(function() {
                targetDom.classList.remove('l-live-search__container--loading');
            });
    }

    // Returns a function, that, as long as it continues to be invoked, will not
    // be triggered. The function will be called after it stops being called for
    // N milliseconds. If `immediate` is passed, trigger the function on the
    // leading edge, instead of the trailing.
     debounce(func, wait, immediate) {
        let timeout;
        return function() {
            let context = this, args = arguments;
            let later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            let callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    };
}

export default Search;
