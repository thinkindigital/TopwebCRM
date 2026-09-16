/* V-06: busca segura do TopwebChat. Sem build; carregado via asset com defer.
   Listeners delegados no document: o shell admin (Vue) pode substituir nos
   do cabecalho no mount; listeners no no morrem com a troca. */
(function () {
    var timer = null;
    var activeIndex = -1;

    function els() {
        var root = document.querySelector('[data-topwebchat-search]');
        if (! root) {
            return null;
        }

        var input = root.querySelector('#topwebchat-search-input');
        var results = root.querySelector('#topwebchat-search-results');

        if (! input || ! results) {
            return null;
        }

        return { root: root, input: input, results: results };
    }

    function close(restoreFocus) {
        var e = els();
        if (! e) {
            return;
        }

        e.results.classList.add('hidden');
        e.results.innerHTML = '';
        e.input.setAttribute('aria-expanded', 'false');
        e.input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
        if (restoreFocus) {
            e.input.focus();
        }
    }

    function render(items, emptyText) {
        var e = els();
        if (! e) {
            return;
        }

        e.results.innerHTML = '';
        activeIndex = -1;
        e.input.removeAttribute('aria-activedescendant');

        if (! items.length) {
            var empty = document.createElement('p');
            empty.className = 'p-4 text-sm text-gray-500';
            empty.textContent = emptyText;
            e.results.appendChild(empty);
        }

        items.forEach(function (item, index) {
            var link = document.createElement('a');
            link.href = item.url;
            link.setAttribute('role', 'option');
            link.id = 'topwebchat-search-option-' + index;
            link.setAttribute('aria-selected', 'false');
            link.dataset.index = index;
            link.className = 'block border-b border-gray-100 p-3 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950';

            var title = document.createElement('p');
            title.className = 'truncate text-sm font-semibold text-gray-800 dark:text-white';
            title.textContent = item.title;
            link.appendChild(title);

            if (item.subtitle) {
                var subtitle = document.createElement('p');
                subtitle.className = 'truncate text-xs text-gray-500';
                subtitle.textContent = item.subtitle;
                link.appendChild(subtitle);
            }

            e.results.appendChild(link);
        });

        e.results.classList.remove('hidden');
        e.input.setAttribute('aria-expanded', 'true');
    }

    function search() {
        var e = els();
        if (! e) {
            return;
        }

        var term = e.input.value.trim();

        if (term.length < 2) {
            close();
            return;
        }

        fetch(e.input.dataset.searchUrl + '?q=' + encodeURIComponent(term), {
            headers: { Accept: 'application/json' },
        }).then(function (response) {
            return response.ok ? response.json() : { data: [] };
        }).then(function (payload) {
            render(payload.data || [], e.input.dataset.searchEmpty);
        }).catch(close);
    }

    document.addEventListener('input', function (event) {
        if (! event.target || event.target.id !== 'topwebchat-search-input') {
            return;
        }

        clearTimeout(timer);
        timer = setTimeout(search, 250);
    });

    document.addEventListener('keydown', function (event) {
        var e = els();
        if (! e || (event.target !== e.input && ! e.root.contains(event.target))) {
            return;
        }

        var options = e.results.querySelectorAll('[role="option"]');

        if (event.key === 'Escape') {
            close(true);
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = event.key === 'ArrowDown'
                ? Math.min(activeIndex + 1, options.length - 1)
                : Math.max(activeIndex - 1, 0);

            options.forEach(function (option, index) {
                var selected = index === activeIndex;
                option.classList.toggle('bg-gray-100', selected);
                option.setAttribute('aria-selected', selected ? 'true' : 'false');
            });

            if (options[activeIndex]) {
                e.input.setAttribute('aria-activedescendant', options[activeIndex].id);
            }

            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0 && options[activeIndex]) {
            event.preventDefault();
            window.location.href = options[activeIndex].href;
        }
    });

    document.addEventListener('click', function (event) {
        var e = els();
        if (! e || e.root.contains(event.target)) {
            return;
        }

        close();
    });
})();
