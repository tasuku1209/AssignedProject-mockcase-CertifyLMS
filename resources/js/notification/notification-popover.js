/**
 * 通知ポップオーバーを初期化する。
 */
export function initNotificationPopover() {
    const panel = document.querySelector(
        '[data-notification-popover-panel]',
    );

    if (!panel) {
        return;
    }

    const trigger = document.querySelector(
        '[data-notification-popover-trigger]',
    );

    const items = panel.querySelector(
        '[data-notification-popover-items]',
    );

    const loading = panel.querySelector(
        '[data-notification-popover-loading]',
    );

    const empty = panel.querySelector(
        '[data-notification-popover-empty]',
    );

    const template = panel.querySelector(
        '[data-notification-popover-row-template]',
    );

    const tabs = panel.querySelectorAll(
        '[data-notification-popover-tab]',
    );

    const markAllButton = panel.querySelector(
        '[data-notification-popover-mark-all]',
    );

    const unreadCountElements = document.querySelectorAll(
        '[data-notification-popover-unread-count]',
    );

    const badge = document.querySelector(
        '[data-notification-popover-badge]',
    );

    if (!trigger || !items || !loading || !empty || !template) {
        return;
    }

    const apiUrl = '/api/v1/notifications';
    let currentTab = 'all';

    /*
     * ポップオーバーの開閉
     */
    trigger.addEventListener('click', () => {
        const isHidden = panel.classList.contains('hidden');

        if (isHidden) {
            openPopover();
            loadNotifications(currentTab);
        } else {
            closePopover();
        }
    });

    /*
     * タブ切り替え
     */
    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const selectedTab = tab.dataset.notificationPopoverTab;

            if (!selectedTab || selectedTab === currentTab) {
                return;
            }

            currentTab = selectedTab;
            updateTabState();
            loadNotifications(currentTab);
        });
    });

    /*
     * 全件既読
     */
    if (markAllButton) {
        markAllButton.addEventListener('click', async () => {
            try {
                await markAllAsRead();
                await loadNotifications(currentTab);
            } catch (error) {
                console.error(
                    'Failed to mark all notifications as read:',
                    error,
                );
            }
        });
    }

    /*
     * ポップオーバー外をクリックしたら閉じる
     */
    document.addEventListener('click', (event) => {
        if (
            !panel.contains(event.target) &&
            !trigger.contains(event.target)
        ) {
            closePopover();
        }
    });

    /*
     * Escapeキーで閉じる
     */
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePopover();
        }
    });

    /*
     * 通知一覧をAPIから取得する。
     */
    async function loadNotifications(tab) {
        showLoading();

        try {
            const response = await fetch(
                `${apiUrl}?tab=${encodeURIComponent(tab)}`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            renderNotifications(data.data ?? []);
            updateUnreadCount(data.unread_count ?? 0);
        } catch (error) {
            console.error(
                'Failed to fetch notifications:',
                error,
            );

            clearItems();
            empty.textContent =
                '通知の取得に失敗しました。';
            empty.classList.remove('hidden');
        } finally {
            loading.classList.add('hidden');
        }
    }

    /*
     * 通知一覧を画面に表示する。
     */
    function renderNotifications(notifications) {
        clearItems();

        if (notifications.length === 0) {
            empty.textContent = '通知はありません。';
            empty.classList.remove('hidden');
            return;
        }

        empty.classList.add('hidden');

        notifications.forEach((notification) => {
            const element = createNotificationElement(
                notification,
                template,
            );

            if (element) {
                items.appendChild(element);
            }
        });
    }

    /*
     * 通知1件分のHTML要素を作成する。
     */
    function createNotificationElement(
        notification,
        notificationTemplate,
    ) {
        const element =
            notificationTemplate.content.cloneNode(true);

        const row = element.querySelector(
            '[data-notification-popover-row]',
        );

        if (!row) {
            return null;
        }

        row.href = notification.url ?? '#';
        row.dataset.notificationId = notification.id;
        row.dataset.unread = String(!notification.is_read);

        const title = row.querySelector(
            '[data-notification-popover-row-title]',
        );

        if (title) {
            title.textContent = notification.title ?? '';
        }

        const message = row.querySelector(
            '[data-notification-popover-row-message]',
        );

        if (message) {
            message.textContent = notification.preview ?? '';
        }

        const time = row.querySelector(
            '[data-notification-popover-row-time]',
        );

        if (time) {
            time.textContent = formatElapsedTime(
                notification.created_at,
            );
        }

        const dot = row.querySelector(
            '[data-notification-popover-row-dot]',
        );

        if (notification.is_read) {
            if (dot) {
                dot.classList.add('invisible');
            }
        } else {
            row.classList.add('bg-primary-50/30');
        }

        row.addEventListener('click', async (event) => {
            if (notification.is_read) {
                return;
            }

            event.preventDefault();

            try {
                await markAsRead(notification.id);

                window.location.href =
                    notification.url ?? '#';
            } catch (error) {
                console.error(
                    'Failed to mark notification as read:',
                    error,
                );
            }
        });

        return element;
    }

    /*
     * 通知を既読にする。
     */
    async function markAsRead(notificationId) {
        await ensureCsrfCookie();

        const response = await fetch(
            `${apiUrl}/${encodeURIComponent(notificationId)}/read`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        updateUnreadCount(data.unread_count ?? 0);
    }

    /*
     * すべての通知を既読にする。
     */
    async function markAllAsRead() {
        await ensureCsrfCookie();

        const response = await fetch(
            `${apiUrl}/read-all`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        updateUnreadCount(data.unread_count ?? 0);
    }

    /*
     * SanctumのCSRF Cookieを取得する。
     */
    async function ensureCsrfCookie() {
        if (getXsrfToken()) {
            return;
        }

        const response = await fetch(
            '/sanctum/csrf-cookie',
            {
                method: 'GET',
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            throw new Error(
                `CSRF cookie request failed: HTTP ${response.status}`,
            );
        }
    }

    /*
     * XSRF-TOKEN CookieからCSRFトークンを取得する。
     */
    function getXsrfToken() {
        const cookie = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='));

        if (!cookie) {
            return '';
        }

        return decodeURIComponent(
            cookie.substring('XSRF-TOKEN='.length),
        );
    }

    /*
     * 未読件数を更新する。
     */
    function updateUnreadCount(count) {
        unreadCountElements.forEach((element) => {
            element.textContent = String(count);
        });

        if (!badge) {
            return;
        }

        badge.textContent = String(count);
        badge.classList.toggle('hidden', count === 0);
    }

    /*
     * 通知一覧をクリアする。
     */
    function clearItems() {
        items.innerHTML = '';
    }

    /*
     * ローディング表示を開始する。
     */
    function showLoading() {
        clearItems();
        empty.classList.add('hidden');
        loading.classList.remove('hidden');
    }

    /*
     * タブの選択状態を更新する。
     */
    function updateTabState() {
        tabs.forEach((tab) => {
            const isSelected =
                tab.dataset.notificationPopoverTab ===
                currentTab;

            tab.setAttribute(
                'aria-selected',
                String(isSelected),
            );
        });
    }

    /*
     * ポップオーバーを開く。
     */
    function openPopover() {
        panel.classList.remove('hidden');
        panel.style.display = 'flex';

        requestAnimationFrame(() => {
            panel.classList.remove(
                'opacity-0',
                '-translate-y-1',
            );
            panel.classList.add(
                'opacity-100',
                'translate-y-0',
            );
        });
    }

    /*
     * ポップオーバーを閉じる。
     */
    function closePopover() {
        panel.classList.remove(
            'opacity-100',
            'translate-y-0',
        );
        panel.classList.add(
            'opacity-0',
            '-translate-y-1',
        );

        setTimeout(() => {
            panel.classList.add('hidden');
            panel.style.display = 'none';
        }, 150);
    }

    /*
     * 通知の経過時間を表示する。
     */
    function formatElapsedTime(createdAt) {
        if (!createdAt) {
            return '';
        }

        const created = new Date(createdAt);
        const now = new Date();
        const diffSeconds = Math.max(
            0,
            Math.floor((now - created) / 1000),
        );

        if (diffSeconds < 60) {
            return `${diffSeconds}秒前`;
        }

        const diffMinutes = Math.floor(
            diffSeconds / 60,
        );

        if (diffMinutes < 60) {
            return `${diffMinutes}分前`;
        }

        const diffHours = Math.floor(
            diffMinutes / 60,
        );

        if (diffHours < 24) {
            return `${diffHours}時間前`;
        }

        const diffDays = Math.floor(
            diffHours / 24,
        );

        if (diffDays < 30) {
            return `${diffDays}日前`;
        }

        const diffMonths = Math.floor(
            diffDays / 30,
        );

        if (diffMonths < 12) {
            return `${diffMonths}か月前`;
        }

        const diffYears = Math.floor(
            diffMonths / 12,
        );

        return `${diffYears}年前`;
    }
}