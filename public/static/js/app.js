(function ($) {
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            return decodeURIComponent(parts.pop().split(";").shift());
        }
        return "";
    }

    function getCsrfToken() {
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        return (metaToken && metaToken.getAttribute("content")) || getCookie("XSRF-TOKEN") || getCookie("csrftoken");
    }

    function showToast(message, type = "info") {
        const stack = document.getElementById("ui-feedback-stack");
        if (!stack) {
            window.alert(message);
            return;
        }

        const toast = document.createElement("div");
        toast.className = `ui-toast toast-${type}`;
        toast.textContent = message;
        stack.appendChild(toast);

        window.requestAnimationFrame(function () {
            toast.classList.add("is-visible");
        });

        window.setTimeout(function () {
            toast.classList.remove("is-visible");
            window.setTimeout(function () {
                toast.remove();
            }, 220);
        }, 3200);
    }

    function setInlineFeedback(target, message, success) {
        if (!target) {
            showToast(message, success ? "success" : "danger");
            return;
        }

        target.className = `ajax-feedback alert ${success ? "alert-success" : "alert-danger"} is-visible`;
        target.textContent = message;
    }

    function getFeedbackTarget(form) {
        const context = form.closest("[data-feedback-context]");
        if (!context) {
            return document.getElementById("event-action-feedback");
        }
        return context.querySelector(".ajax-feedback") || document.getElementById("event-action-feedback");
    }

    function setSubmitButtonState(form, isLoading) {
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!submitButton) {
            return;
        }

        if (isLoading) {
            submitButton.dataset.originalLabel = submitButton.dataset.originalLabel || submitButton.textContent;
            submitButton.textContent = submitButton.dataset.loadingLabel || submitButton.getAttribute("data-loading-label") || "Attendere...";
            submitButton.disabled = true;
            form.setAttribute("aria-busy", "true");
        } else {
            submitButton.textContent = submitButton.dataset.originalLabel || submitButton.textContent;
            submitButton.disabled = false;
            form.setAttribute("aria-busy", "false");
        }
    }

    function updateNotificationBadge(unreadCount) {
        const badge = document.getElementById("notification-badge");
        if (!badge) {
            return;
        }

        badge.textContent = unreadCount;
        if (unreadCount > 0) {
            badge.classList.remove("is-hidden");
        } else {
            badge.classList.add("is-hidden");
        }
    }

    async function refreshNotificationSummary() {
        const trigger = document.querySelector("[data-notification-summary-url]");
        if (!trigger) {
            return;
        }

        try {
            const response = await fetch(trigger.dataset.notificationSummaryUrl, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            if (!response.ok) {
                return;
            }
            const result = await response.json();
            if (result.success) {
                updateNotificationBadge(result.data.unread_count);
            }
        } catch (error) {
            // Manteniamo l'ultimo badge noto senza interrompere l'interfaccia.
        }
    }

    async function loadNotificationPanel() {
        const trigger = document.querySelector("[data-notification-panel-url]");
        const panelBody = document.getElementById("notification-panel-body");
        if (!trigger || !panelBody) {
            return;
        }

        panelBody.innerHTML = '<div class="notification-loading text-muted small">Caricamento notifiche in corso...</div>';
        try {
            const response = await fetch(trigger.dataset.notificationPanelUrl, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            if (!response.ok) {
                throw new Error("request_failed");
            }
            panelBody.innerHTML = await response.text();
        } catch (error) {
            panelBody.innerHTML = '<div class="ajax-feedback alert alert-danger is-visible">Impossibile caricare le notifiche in questo momento.</div>';
        }
    }

    async function markNotification(url, { reloadPanel = true } = {}) {
        try {
            const response = await fetch(url, {
                method: "POST",
                headers: {
                    "X-CSRFToken": getCsrfToken(),
                    "X-CSRF-TOKEN": getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            const result = await response.json();
            showToast(result.message, result.success ? "success" : "danger");
            if (!result.success) {
                return;
            }
            updateNotificationBadge(result.data.unread_count);
            if (reloadPanel) {
                loadNotificationPanel();
            }
        } catch (error) {
            showToast("Errore durante l'aggiornamento delle notifiche.", "danger");
        }
    }

    async function postJsonForm(form) {
        const feedbackNode = getFeedbackTarget(form);
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        setSubmitButtonState(form, true);

        try {
            const response = await fetch(form.action, {
                method: form.method || "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRFToken": getCsrfToken(),
                    "X-CSRF-TOKEN": getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json();
            setInlineFeedback(feedbackNode, result.message, result.success);

            if (!result.success) {
                return;
            }

            showToast(result.message, "success");
            window.setTimeout(function () {
                window.location.reload();
            }, 650);
        } catch (error) {
            setInlineFeedback(feedbackNode, "Si e verificato un errore di rete. Riprova.", false);
        } finally {
            setSubmitButtonState(form, false);
        }
    }

    function highlightCurrentNavigation() {
        const currentPath = window.location.pathname;
        let bestLink = null;
        let bestLength = 0;
        document.querySelectorAll(".nav-link").forEach(function (link) {
            const href = link.getAttribute("href");
            if (!href) {
                return;
            }

            const isHome = href === "/";
            const matches = isHome ? currentPath === "/" : currentPath.startsWith(href);
            if (matches && href.length > bestLength) {
                bestLink = link;
                bestLength = href.length;
            }
        });
        if (bestLink) {
            bestLink.classList.add("active");
            bestLink.setAttribute("aria-current", "page");
        }
    }

    function toggleCustomFieldOptions(row) {
        const select = row.querySelector("[data-custom-field-type]");
        const optionsWrapper = row.querySelector("[data-custom-field-options]");
        const textarea = optionsWrapper ? optionsWrapper.querySelector("textarea") : null;
        if (!select || !optionsWrapper || !textarea) {
            return;
        }

        const isSelect = select.value === "select";
        optionsWrapper.classList.toggle("is-hidden", !isSelect);
        textarea.disabled = !isSelect;
        if (!isSelect) {
            textarea.value = "";
        }
    }

    function bindCustomFieldFormset() {
        const formset = document.querySelector("[data-custom-field-formset]");
        if (!formset) {
            return;
        }

        const list = formset.querySelector("[data-custom-field-list]");
        const templateNode = formset.querySelector("[data-empty-custom-field-template]");
        const totalFormsInput = document.getElementById(`id_${formset.dataset.prefix}-TOTAL_FORMS`);
        if (!list || !templateNode || !totalFormsInput) {
            return;
        }

        function initializeRow(row) {
            toggleCustomFieldOptions(row);
        }

        list.querySelectorAll("[data-custom-field-row]").forEach(initializeRow);

        document.querySelectorAll("[data-add-custom-field]").forEach(function (button) {
            button.addEventListener("click", function () {
                const nextIndex = parseInt(totalFormsInput.value, 10);
                const html = templateNode.innerHTML.replace(/__prefix__/g, nextIndex);
                const fragment = document.createRange().createContextualFragment(html);
                const row = fragment.querySelector("[data-custom-field-row]");
                list.appendChild(fragment);
                totalFormsInput.value = nextIndex + 1;
                if (row) {
                    const orderInput = row.querySelector('input[name$="[display_order]"]');
                    if (orderInput && !orderInput.value) {
                        orderInput.value = nextIndex + 1;
                    }
                    initializeRow(row);
                }
            });
        });

        $(document).on("change", "[data-custom-field-type]", function (event) {
            const row = event.currentTarget.closest("[data-custom-field-row]");
            if (row) {
                toggleCustomFieldOptions(row);
            }
        });

        $(document).on("click", "[data-remove-custom-field]", function (event) {
            const row = event.currentTarget.closest("[data-custom-field-row]");
            if (!row) {
                return;
            }
            const deleteInput = row.querySelector('input[type="checkbox"][name$="-DELETE"], input[type="checkbox"][name$="[DELETE]"]');
            if (deleteInput) {
                deleteInput.checked = true;
            }
            row.classList.add("is-hidden");
        });
    }

    $(document).on("submit", "[data-json-form]", function (event) {
        event.preventDefault();
        postJsonForm(event.currentTarget);
    });

    $(document).on("submit", "[data-confirm-message]", function (event) {
        if (!window.confirm(event.currentTarget.dataset.confirmMessage)) {
            event.preventDefault();
        }
    });

    let filterRequestTimer;

    $(document).on("input change", "[data-filter-form] :input", function () {
        const form = $(this).closest("[data-filter-form]");
        const targetSelector = form.data("target");
        const target = document.querySelector(targetSelector);

        window.clearTimeout(filterRequestTimer);
        filterRequestTimer = window.setTimeout(function () {
            if (target) {
                target.classList.add("is-loading");
                target.setAttribute("aria-busy", "true");
            }

            $.ajax({
                url: window.location.pathname,
                method: "GET",
                data: form.serialize(),
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
                success: function (html) {
                    $(targetSelector).html(html);
                },
                error: function () {
                    showToast("Impossibile aggiornare i risultati in questo momento.", "danger");
                },
                complete: function () {
                    if (target) {
                        target.classList.remove("is-loading");
                        target.setAttribute("aria-busy", "false");
                    }
                },
            });
        }, 220);
    });

    $(document).on("focus", "[data-status-select]", function (event) {
        event.currentTarget.dataset.previousValue = event.currentTarget.value;
    });

    $(document).on("change", "[data-status-select]", async function (event) {
        const select = event.currentTarget;
        const feedbackNode = document.getElementById("manage-status-feedback");
        const selectedOption = Array.from(select.options).find(function (option) {
            return option.defaultSelected;
        });
        const previousValue = select.dataset.previousValue || (selectedOption ? selectedOption.value : select.value);

        select.disabled = true;
        select.classList.add("is-saving");

        try {
            const response = await fetch(select.dataset.url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRFToken": getCsrfToken(),
                    "X-CSRF-TOKEN": getCsrfToken(),
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({ status: select.value }),
            });

            const result = await response.json();
            setInlineFeedback(feedbackNode, result.message, result.success);
            showToast(result.message, result.success ? "success" : "danger");
            if (result.success) {
                select.dataset.previousValue = select.value;
                Array.from(select.options).forEach(function (option) {
                    option.defaultSelected = option.value === select.value;
                });
            } else {
                select.value = previousValue;
            }
        } catch (error) {
            select.value = previousValue;
            setInlineFeedback(feedbackNode, "Errore durante il salvataggio dello stato.", false);
            showToast("Errore durante il salvataggio dello stato.", "danger");
        } finally {
            select.disabled = false;
            select.classList.remove("is-saving");
        }
    });

    $(document).on("click", "[data-notification-mark-read-url]", function (event) {
        event.preventDefault();
        markNotification(event.currentTarget.dataset.notificationMarkReadUrl);
    });

    $(document).on("click", "[data-notification-mark-all-url]", function (event) {
        event.preventDefault();
        markNotification(event.currentTarget.dataset.notificationMarkAllUrl);
    });

    document.addEventListener("shown.bs.offcanvas", function (event) {
        if (event.target && event.target.id === "notificationPanel") {
            loadNotificationPanel();
        }
    });

    highlightCurrentNavigation();
    bindCustomFieldFormset();
    refreshNotificationSummary();

    if (document.querySelector("[data-notification-summary-url]")) {
        window.setInterval(refreshNotificationSummary, 60000);
        window.addEventListener("focus", refreshNotificationSummary);
    }
})(jQuery);
