(function () {
    "use strict";

    const app = document.getElementById("feedApp");
    if (!app) {
        return;
    }

    const state = {
        tab: app.dataset.initialTab || "home",
        page: 1,
        hasMore: true,
        loading: false,
        loadingComments: new Set(),
    };

    const appUrl = (path) => {
        if (window.Carvasilva && typeof window.Carvasilva.url === "function") {
            return window.Carvasilva.url(path);
        }
        return path;
    };

    const feedList = document.getElementById("feedList");
    const sentinel = document.getElementById("feedSentinel");
    const errorBox = document.getElementById("feedError");
    const loadingBox = document.getElementById("feedLoading");
    const composerForm = document.getElementById("composerForm");
    const composerTextarea = document.getElementById("composerContent");
    const counter = document.getElementById("composerCounter");
    const csrfToken = app.dataset.csrf || "";
    const maxChars = Number(app.dataset.maxChars || "280");
    const anonymousMode = app.dataset.anonymousMode === "1";

    const html = (value) =>
        String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");

    const formatCredits = (value) => `\u20A2 ${Number(value || 0).toLocaleString("pt-BR")}`;

    const showError = (message) => {
        if (!errorBox) return;
        errorBox.hidden = false;
        errorBox.textContent = message || "Erro inesperado.";
        if (window.Carvasilva && typeof window.Carvasilva.notifyError === "function") {
            window.Carvasilva.notifyError(message || "Erro inesperado.");
        }
    };

    const hideError = () => {
        if (!errorBox) return;
        errorBox.hidden = true;
        errorBox.textContent = "";
    };

    const setLoading = (status) => {
        state.loading = status;
        if (loadingBox) {
            loadingBox.hidden = !status;
        }
    };

    const postBadgeMarkup = (post) => {
        const badges = [];

        if (post.type === "credit_flex") {
            badges.push('<span class="badge badge--gold post-badge-flex"><i class="fa-solid fa-coins" aria-hidden="true"></i> Credit Flex</span>');
        }

        if (post.is_anonymous) {
            badges.push('<span class="badge post-badge-anon"><i class="fa-solid fa-user-secret" aria-hidden="true"></i> Anonimo</span>');
        }

        return badges.join(" ");
    };

    const commentMarkup = (comment) => `
        <div class="comment-item">
            <div class="comment-meta">${html(comment.author || "Anonimo")} | ${html(comment.time_ago || "")}</div>
            <div class="comment-content">${html(comment.content || "")}</div>
        </div>
    `;

    const buildPostCard = (post) => {
        const user = post.user || {};
        const isAnonymous = Boolean(Number(post.is_anonymous));
        const cardClasses = ["post", "post-card"];

        if (post.type === "credit_flex") {
            cardClasses.push("post-card--credit-flex");
        }
        if (isAnonymous) {
            cardClasses.push("post-card--anonymous");
        }

        const avatar = isAnonymous
            ? appUrl("/assets/img/avatars/default-avatar.svg")
            : (user.avatar || appUrl("/assets/img/avatars/default-avatar.svg"));
        const author = isAnonymous ? "Anonimo Misterioso" : (user.username || "Usuario");
        const room = !isAnonymous && user.room ? ` | Turma ${html(user.room)}` : "";
        const credits = !isAnonymous && Number(user.credits || 0) > 0
            ? `<span class="credits">${formatCredits(user.credits)}</span>`
            : "";
        const image = post.image_path
            ? `<img src="${html(post.image_path)}" alt="Imagem da postagem" class="post-image">`
            : "";

        const creditFlexExtra = post.type === "credit_flex"
            ? `<div class="post-credit-flex-box">
                   <span>Saldo atual</span>
                   <span class="post-credit-flex-amount">${formatCredits(post.flex_balance || user.credits || 0)}</span>
               </div>`
            : "";

        const tipBtn = isAnonymous
            ? ""
            : `<button class="btn btn--ghost post-action" data-action="tip" data-post-id="${post.id}">
                    <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i><span>Tip</span>
               </button>`;

        const deleteBtn = post.can_delete
            ? `<button class="btn btn--ghost post-action" data-action="delete" data-post-id="${post.id}">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i><span>Excluir</span>
               </button>`
            : "";

        return `
            <article class="${cardClasses.join(" ")}" data-post-id="${post.id}">
                <header class="post-header">
                    <div class="post-author">
                        <img src="${html(avatar)}" alt="Avatar de ${html(author)}" class="avatar-sm">
                        <div class="post-author-info">
                            <div class="post-author-name">
                                <span>${html(author)}</span>
                                ${postBadgeMarkup(post)}
                            </div>
                            <span class="post-time">${html(post.time_ago || "")}${room}</span>
                        </div>
                    </div>
                    <div>${credits}</div>
                </header>

                <p class="post-content">${html(post.content || "")}</p>
                ${creditFlexExtra}
                ${image}

                <footer class="post-actions">
                    <button class="btn btn--ghost post-action ${post.user_liked ? "is-liked" : ""}" data-action="like" data-post-id="${post.id}">
                        <i class="fa-solid fa-heart" aria-hidden="true"></i>
                        <span data-role="like-count">${Number(post.like_count || 0)}</span>
                    </button>

                    <button class="btn btn--ghost post-action" data-action="show-comments" data-post-id="${post.id}">
                        <i class="fa-regular fa-comment" aria-hidden="true"></i>
                        <span data-role="comment-count">${Number(post.comment_count || 0)}</span>
                    </button>

                    <button class="btn btn--ghost post-action" data-action="add-comment" data-post-id="${post.id}">
                        <i class="fa-solid fa-reply" aria-hidden="true"></i><span>Comentar</span>
                    </button>

                    ${tipBtn}
                    ${deleteBtn}
                </footer>

                <div class="comment-list" data-role="comments" hidden></div>
            </article>
        `;
    };

    const appendPosts = (posts) => {
        if (!feedList) return;

        if (state.page === 1 && posts.length === 0) {
            feedList.innerHTML = `
                <div class="feed-empty card">
                    Nenhuma postagem encontrada nesta aba.
                </div>
            `;
            return;
        }

        const markup = posts.map(buildPostCard).join("");
        feedList.insertAdjacentHTML("beforeend", markup);
    };

    const parseResponse = async (response) => {
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || "Falha ao processar requisicao.");
        }
        return payload.data || {};
    };

    const loadPosts = async (reset = false) => {
        if (state.loading || (!state.hasMore && !reset)) {
            return;
        }

        if (reset) {
            state.page = 1;
            state.hasMore = true;
            if (feedList) feedList.innerHTML = "";
        }

        hideError();
        setLoading(true);

        try {
            const endpoint = `/api/post.php?action=feed&tab=${encodeURIComponent(state.tab)}&page=${state.page}&limit=20`;
            const response = await fetch(endpoint, { headers: { Accept: "application/json" } });
            const data = await parseResponse(response);

            const posts = Array.isArray(data.posts) ? data.posts : [];
            appendPosts(posts);

            state.hasMore = Boolean(data.has_more);
            state.page += 1;
        } catch (error) {
            showError(error.message || "Nao foi possivel carregar o feed.");
        } finally {
            setLoading(false);
        }
    };

    const updateCounter = () => {
        if (!composerTextarea || !counter) return;
        const length = composerTextarea.value.length;
        counter.textContent = `${length}/${maxChars}`;
        counter.classList.toggle("is-limit", length > maxChars);
    };

    const submitComposer = async (action = "create") => {
        if (!composerForm) return;

        const formData = new FormData(composerForm);
        formData.set("csrf_token", csrfToken);

        const endpoint = `/api/post.php?action=${encodeURIComponent(action)}`;

        const submitButton = composerForm.querySelector("[type='submit']");
        if (submitButton) submitButton.disabled = true;

        try {
            const response = await fetch(endpoint, {
                method: "POST",
                headers: { Accept: "application/json" },
                body: formData,
            });
            await parseResponse(response);

            composerForm.reset();
            updateCounter();
            if (window.Carvasilva && typeof window.Carvasilva.notifySuccess === "function") {
                window.Carvasilva.notifySuccess("Postagem publicada com sucesso.");
            }
            await loadPosts(true);
        } catch (error) {
            showError(error.message || "Nao foi possivel publicar.");
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    };

    const toggleLike = async (postId, button) => {
        const formData = new FormData();
        formData.set("post_id", String(postId));
        formData.set("csrf_token", csrfToken);

        const response = await fetch("/api/like.php?action=toggle", {
            method: "POST",
            headers: { Accept: "application/json" },
            body: formData,
        });
        const data = await parseResponse(response);

        const liked = Boolean(data.liked);
        button.classList.toggle("is-liked", liked);
        button.classList.add("like-pop");
        setTimeout(() => button.classList.remove("like-pop"), 220);

        const countEl = button.querySelector("[data-role='like-count']");
        if (countEl) {
            countEl.textContent = String(Number(data.like_count || 0));
        }
    };

    const loadComments = async (postId, wrapper) => {
        if (state.loadingComments.has(postId)) return;
        state.loadingComments.add(postId);

        try {
            const response = await fetch(`/api/comment.php?post_id=${encodeURIComponent(postId)}&page=1`, {
                headers: { Accept: "application/json" },
            });
            const data = await parseResponse(response);
            const comments = Array.isArray(data.comments) ? data.comments : [];
            wrapper.innerHTML = comments.length
                ? comments.map(commentMarkup).join("")
                : `<div class="comment-item"><div class="comment-content">Sem comentarios ainda.</div></div>`;
        } catch (error) {
            wrapper.innerHTML = `<div class="comment-item"><div class="comment-content">${html(error.message)}</div></div>`;
        } finally {
            state.loadingComments.delete(postId);
        }
    };

    const sendComment = async (postId, countEl, commentsWrapper) => {
        const content = window.prompt("Escreva seu comentario (ate 500 caracteres):");
        if (!content) return;

        const formData = new FormData();
        formData.set("post_id", String(postId));
        formData.set("content", content);
        formData.set("is_anonymous", anonymousMode ? "1" : "0");
        formData.set("csrf_token", csrfToken);

        const response = await fetch("/api/comment.php?action=create", {
            method: "POST",
            headers: { Accept: "application/json" },
            body: formData,
        });
        const data = await parseResponse(response);
        const comment = data.comment || null;

        if (comment && commentsWrapper) {
            commentsWrapper.hidden = false;
            if (commentsWrapper.querySelector(".comment-content")?.textContent === "Sem comentarios ainda.") {
                commentsWrapper.innerHTML = "";
            }
            commentsWrapper.insertAdjacentHTML("afterbegin", commentMarkup(comment));
        }

        if (countEl) {
            countEl.textContent = String(Number(countEl.textContent || "0") + 1);
        }
    };

    const deletePost = async (postId, card) => {
        if (!window.confirm("Excluir esta postagem?")) return;

        const body = new URLSearchParams();
        body.set("csrf_token", csrfToken);

        const response = await fetch(`/api/post.php?action=delete&id=${encodeURIComponent(postId)}`, {
            method: "DELETE",
            headers: { Accept: "application/json", "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString(),
        });
        await parseResponse(response);

        card.remove();
    };

    const sendTip = async (postId) => {
        const raw = window.prompt("Valor do tip (minimo \u20A2 5):");
        if (!raw) return;

        const amount = Number(raw);
        if (!Number.isFinite(amount) || amount < 5) {
            showError("Tip invalido. O minimo e \u20A2 5.");
            return;
        }

        const formData = new FormData();
        formData.set("post_id", String(postId));
        formData.set("amount", String(Math.floor(amount)));
        formData.set("csrf_token", csrfToken);

        const response = await fetch("/api/credits.php?action=tip", {
            method: "POST",
            headers: { Accept: "application/json" },
            body: formData,
        });
        const data = await parseResponse(response);

        if (window.Carvasilva && typeof window.Carvasilva.updateBalanceDisplay === "function") {
            window.Carvasilva.updateBalanceDisplay(Number(data.new_balance || 0), { animate: true });
        }
        if (window.Carvasilva && typeof window.Carvasilva.notifySuccess === "function") {
            window.Carvasilva.notifySuccess("Tip enviado com sucesso.");
        }
    };

    const handleFeedActions = async (event) => {
        const button = event.target.closest("[data-action]");
        if (!button || !feedList) return;

        const action = button.dataset.action;
        const postId = Number(button.dataset.postId || "0");
        if (!postId) return;

        const card = button.closest("[data-post-id]");
        if (!card) return;

        try {
            hideError();

            if (action === "like") {
                await toggleLike(postId, button);
                return;
            }

            if (action === "show-comments") {
                const wrapper = card.querySelector("[data-role='comments']");
                if (!wrapper) return;
                const willShow = wrapper.hidden;
                wrapper.hidden = !wrapper.hidden;
                if (willShow && !wrapper.dataset.loaded) {
                    await loadComments(postId, wrapper);
                    wrapper.dataset.loaded = "1";
                }
                return;
            }

            if (action === "add-comment") {
                const countEl = card.querySelector("[data-role='comment-count']");
                const wrapper = card.querySelector("[data-role='comments']");
                await sendComment(postId, countEl, wrapper);
                return;
            }

            if (action === "delete") {
                await deletePost(postId, card);
                return;
            }

            if (action === "tip") {
                await sendTip(postId);
                return;
            }
        } catch (error) {
            showError(error.message || "Acao indisponivel.");
        }
    };

    const handleComposerExtraActions = async (event) => {
        const button = event.target.closest("[data-composer-action]");
        if (!button) return;

        const action = button.dataset.composerAction;
        if (action === "credit-flex") {
            event.preventDefault();
            await submitComposer("credit_flex");
        }
    };

    if (composerForm) {
        composerForm.addEventListener("submit", async (event) => {
            event.preventDefault();
            await submitComposer(anonymousMode ? "create_anonymous" : "create");
        });
    }

    if (composerTextarea) {
        composerTextarea.addEventListener("input", updateCounter);
        updateCounter();
    }

    if (feedList) {
        feedList.addEventListener("click", (event) => {
            handleFeedActions(event).catch((error) => showError(error.message || "Acao indisponivel."));
        });
    }

    const tabButtons = document.querySelectorAll("[data-feed-tab]");
    tabButtons.forEach((btn) => {
        btn.addEventListener("click", async () => {
            const nextTab = btn.dataset.feedTab || "home";
            if (nextTab === state.tab) return;

            tabButtons.forEach((item) => {
                item.classList.remove("is-active");
                item.setAttribute("aria-selected", "false");
            });

            btn.classList.add("is-active");
            btn.setAttribute("aria-selected", "true");

            state.tab = nextTab;
            await loadPosts(true);
        });
    });

    const extraActions = document.getElementById("composerActions");
    if (extraActions) {
        extraActions.addEventListener("click", handleComposerExtraActions);
    }

    if (sentinel) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries.some((entry) => entry.isIntersecting);
            if (visible) {
                loadPosts(false).catch((error) => showError(error.message));
            }
        }, { rootMargin: "140px" });

        observer.observe(sentinel);
    }

    loadPosts(true).catch((error) => showError(error.message));
})();
