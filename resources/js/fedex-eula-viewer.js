import * as pdfjsLib from 'pdfjs-dist';
import pdfjsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker;

function createPageShell(pageNumber, totalPages) {
    const shell = document.createElement('div');
    shell.className = 'fedex-eula-page mb-6 rounded-xl border border-[#E2E8F0] bg-white p-3 shadow-sm';
    shell.dataset.pageNumber = String(pageNumber);

    const label = document.createElement('p');
    label.className = 'mb-2 text-xs font-semibold uppercase tracking-wide text-[#64748B]';
    label.textContent = `Page ${pageNumber} of ${totalPages}`;

    const canvas = document.createElement('canvas');
    canvas.className = 'mx-auto block max-w-full';

    shell.appendChild(label);
    shell.appendChild(canvas);

    return { shell, canvas };
}

/**
 * Size the scroll viewport so roughly one full document page is visible at a time.
 */
function sizeViewportToFirstPage(scrollRoot, container) {
    if (! scrollRoot || ! container || scrollRoot.classList.contains('fedex-eula-printing')) {
        return;
    }

    const firstPage = container.querySelector('.fedex-eula-page');
    if (! firstPage) {
        return;
    }

    const styles = window.getComputedStyle(scrollRoot);
    const padY = (parseFloat(styles.paddingTop) || 0) + (parseFloat(styles.paddingBottom) || 0);
    const pageHeight = Math.ceil(firstPage.getBoundingClientRect().height);
    if (pageHeight < 1) {
        return;
    }

    // One full page (+ padding). Cap to available window so accept controls remain reachable.
    const target = Math.ceil(pageHeight + padY);
    const available = Math.max(640, Math.floor(window.innerHeight - 140));
    scrollRoot.style.height = `${Math.min(target, available)}px`;
    scrollRoot.style.maxHeight = 'none';
}

async function renderPage(pdf, pageNumber, canvas, scale = 1.35) {
    const page = await pdf.getPage(pageNumber);
    const viewport = page.getViewport({ scale });
    const context = canvas.getContext('2d');

    canvas.height = viewport.height;
    canvas.width = viewport.width;

    await page.render({
        canvasContext: context,
        viewport,
    }).promise;
}

function dispatchState(viewerId, state) {
    window.dispatchEvent(new CustomEvent('fedex-eula-render-state', {
        detail: { viewerId, ...state },
    }));
}

async function postScrollComplete(options) {
    const {
        scrollCompleteUrl,
        csrfToken,
        documentHash,
        totalPages,
        viewerId,
    } = options;

    const response = await fetch(scrollCompleteUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            document_hash: documentHash,
            rendered_page_count: totalPages,
        }),
    });

    if (! response.ok) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload.message || 'Could not record scroll completion. Reload and try again.');
    }

    window.dispatchEvent(new CustomEvent('fedex-eula-scroll-complete', {
        detail: { viewerId },
    }));
}

async function initFedExEulaViewer(root) {
    let config;

    try {
        config = JSON.parse(root.dataset.fedexEulaConfig || '{}');
    } catch (error) {
        dispatchState(root.id, {
            loading: false,
            renderError: 'Unable to start the agreement viewer. Reload the page.',
            pagesRendered: 0,
            pageCountMismatch: false,
        });

        return;
    }

    const {
        viewerId,
        documentUrl,
        expectedPages,
        documentHash,
        scrollCompleteUrl,
        csrfToken,
    } = config;

    const container = root.querySelector('[data-fedex-eula-pages]');
    const sentinel = root.querySelector('[data-fedex-eula-sentinel]');
    const loadingEl = root.querySelector('[data-fedex-eula-loading]');
    const errorEl = root.querySelector('[data-fedex-eula-error]');
    const scrollRoot = root;

    if (! container || ! documentUrl) {
        dispatchState(viewerId, {
            loading: false,
            renderError: 'Agreement viewer could not initialize.',
            pagesRendered: 0,
            pageCountMismatch: false,
        });

        return;
    }

    dispatchState(viewerId, {
        loading: true,
        renderError: null,
        pagesRendered: 0,
        pageCountMismatch: false,
    });

    try {
        const pdf = await pdfjsLib.getDocument({ url: documentUrl, withCredentials: true }).promise;
        const totalPages = pdf.numPages;

        if (totalPages !== expectedPages) {
            dispatchState(viewerId, {
                loading: false,
                renderError: `Document version mismatch: expected ${expectedPages} pages, PDF has ${totalPages}.`,
                pagesRendered: 0,
                pageCountMismatch: true,
            });

            if (loadingEl) {
                loadingEl.classList.add('hidden');
            }

            if (errorEl) {
                errorEl.textContent = `Document version mismatch: expected ${expectedPages} pages, PDF has ${totalPages}.`;
                errorEl.classList.remove('hidden');
            }

            return;
        }

        container.innerHTML = '';

        for (let pageNumber = 1; pageNumber <= totalPages; pageNumber += 1) {
            const { shell, canvas } = createPageShell(pageNumber, totalPages);
            container.appendChild(shell);
            await renderPage(pdf, pageNumber, canvas);

            if (pageNumber === 1) {
                sizeViewportToFirstPage(scrollRoot, container);
            }

            dispatchState(viewerId, {
                loading: pageNumber < totalPages,
                pagesRendered: pageNumber,
                renderError: null,
                pageCountMismatch: false,
            });
        }

        sizeViewportToFirstPage(scrollRoot, container);

        const onResize = () => sizeViewportToFirstPage(scrollRoot, container);
        window.addEventListener('resize', onResize, { passive: true });

        if (loadingEl) {
            loadingEl.classList.add('hidden');
        }

        dispatchState(viewerId, {
            loading: false,
            pagesRendered: totalPages,
            renderError: null,
            pageCountMismatch: false,
        });

        if (! scrollCompleteUrl) {
            return;
        }

        let scrollPosted = false;

        const tryRecordScroll = async () => {
            if (scrollPosted) {
                return;
            }

            const reachedBottom = scrollRoot.scrollTop + scrollRoot.clientHeight >= scrollRoot.scrollHeight - 24;

            if (! reachedBottom) {
                return;
            }

            scrollPosted = true;

            try {
                await postScrollComplete({
                    scrollCompleteUrl,
                    csrfToken,
                    documentHash,
                    totalPages,
                    viewerId,
                });
            } catch (error) {
                scrollPosted = false;
                dispatchState(viewerId, {
                    loading: false,
                    renderError: error.message,
                    pagesRendered: totalPages,
                    pageCountMismatch: false,
                });
            }
        };

        scrollRoot.addEventListener('scroll', tryRecordScroll, { passive: true });

        if (sentinel) {
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    tryRecordScroll();
                }
            }, { root: scrollRoot, threshold: 0.5 });

            observer.observe(sentinel);
        }
    } catch (error) {
        console.error('FedEx EULA viewer failed to load PDF', error);

        dispatchState(viewerId, {
            loading: false,
            renderError: 'Unable to load the FedEx End User License Agreement. Reload the page or contact support.',
            pagesRendered: 0,
            pageCountMismatch: false,
        });

        if (loadingEl) {
            loadingEl.classList.add('hidden');
        }

        if (errorEl) {
            errorEl.textContent = 'Unable to load the agreement PDF.';
            errorEl.classList.remove('hidden');
        }
    }
}

function bootFedExEulaViewers() {
    document.querySelectorAll('[data-fedex-eula-config]').forEach((root) => {
        initFedExEulaViewer(root);
    });
}

function prepareFedExEulaPrint() {
    document.querySelectorAll('[data-fedex-eula-config]').forEach((root) => {
        root.classList.add('fedex-eula-printing');
        root.style.maxHeight = 'none';
        root.style.minHeight = '0';
        root.style.overflow = 'visible';
        root.style.height = 'auto';
    });
}

function resetFedExEulaPrint() {
    document.querySelectorAll('[data-fedex-eula-config]').forEach((root) => {
        root.classList.remove('fedex-eula-printing');
        root.style.maxHeight = '';
        root.style.minHeight = '';
        root.style.overflow = '';
        root.style.height = '';

        const container = root.querySelector('[data-fedex-eula-pages]');
        sizeViewportToFirstPage(root, container);
    });
}

window.addEventListener('beforeprint', prepareFedExEulaPrint);
window.addEventListener('afterprint', resetFedExEulaPrint);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootFedExEulaViewers);
} else {
    bootFedExEulaViewers();
}
