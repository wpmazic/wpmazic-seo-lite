/* WPMazic SEO Admin Scripts */
(function ($) {
    "use strict";

    function markDirtyField($field) {
        $field.css("border-color", "#0284c7");
        var $row = $field.closest("tr");
        if ($row.length) {
            $row.css("background-color", "#f8fbff");
        }
    }

    function attachTableSearch($tableWrap) {
        var $table = $tableWrap.find("table").first();
        if (!$table.length || $tableWrap.find(".wmz-table-filter").length) {
            return;
        }

        var $input = $(
            '<input type="search" class="wmz-input wmz-table-filter" placeholder="Filter rows...">'
        );

        var $holder = $('<div class="tw-mb-3 tw-max-w-sm"></div>').append($input);
        $tableWrap.before($holder);

        $input.on("input", function () {
            var q = ($(this).val() || "").toLowerCase().trim();
            $table.find("tbody tr").each(function () {
                var hay = ($(this).text() || "").toLowerCase();
                $(this).toggle(q === "" || hay.indexOf(q) !== -1);
            });
        });
    }

    function toggleShellGroup($button) {
        var panelId = ($button.attr("data-panel") || "").trim();
        if (!panelId) {
            return;
        }

        var $panel = $("#" + panelId);
        if (!$panel.length) {
            return;
        }

        var expanded = $button.attr("aria-expanded") === "true";
        $button.attr("aria-expanded", expanded ? "false" : "true");
        if (expanded) {
            $panel.attr("hidden", "hidden");
        } else {
            $panel.removeAttr("hidden");
        }
    }

    function findPrimarySaveButton() {
        var $scope = $(".wpmazic-seo-admin .wmz-shell-page-content");
        if (!$scope.length) {
            return $();
        }

        var selectors = [
            "button.button-primary[type='submit']",
            "input.button-primary[type='submit']",
            ".wmz-actions button.button-primary[type='submit']",
            ".wmz-actions input.button-primary[type='submit']"
        ];

        return $scope.find(selectors.join(",")).filter(":visible").first();
    }

    function syncShellSaveTrigger() {
        var $trigger = $(".wpmazic-seo-admin .wmz-shell-save-trigger");
        if (!$trigger.length) {
            return;
        }

        var hasPrimary = findPrimarySaveButton().length > 0;
        $trigger.toggleClass("is-disabled", !hasPrimary);
        $trigger.attr("aria-disabled", hasPrimary ? "false" : "true");
        $trigger.prop("disabled", !hasPrimary);
    }

    function relocateGlobalAdminNotices() {
        var $slot = $(".wpmazic-seo-admin #wmz-shell-global-notices");
        if (!$slot.length) {
            return;
        }

        $("#wpbody-content .notice.notice-warning.is-dismissible").each(function () {
            var $notice = $(this);
            if ($notice.closest("#wmz-shell-global-notices").length || $notice.closest(".wmz-card").length) {
                return;
            }

            $slot.prepend($notice);
        });

        var selectors = [
            "#wpbody-content > .notice",
            "#wpbody-content > .updated",
            "#wpbody-content > .error",
            "#wpbody-content > .update-nag",
            "#wpbody-content > .woocommerce-message",
            "#wpbody-content > .woocommerce-info",
            "#wpbody-content > .woocommerce-error",
            "#wpbody-content > .wrap > .notice",
            "#wpbody-content > .wrap > .updated",
            "#wpbody-content > .wrap > .error",
            "#wpbody-content > .wrap > .update-nag",
            "#wpbody-content > .wrap > .woocommerce-message",
            "#wpbody-content > .wrap > .woocommerce-info",
            "#wpbody-content > .wrap > .woocommerce-error"
        ];

        $(selectors.join(",")).each(function () {
            var $notice = $(this);
            if ($notice.closest(".wmz-shell").length || $notice.closest("#wmz-shell-global-notices").length) {
                return;
            }

            $slot.append($notice);
        });
    }

    function parseJsonAttribute(element, attributeName) {
        if (!element) {
            return {};
        }

        var raw = element.getAttribute(attributeName);
        if (!raw) {
            return {};
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            return {};
        }
    }

    function mountChart(canvasId, config) {
        if (typeof window.Chart === "undefined") {
            return;
        }

        var canvas = document.getElementById(canvasId);
        if (!canvas) {
            return;
        }

        new window.Chart(canvas.getContext("2d"), config);
    }

    function initDashboardCharts() {
        var root = document.getElementById("wmz-dashboard-charts");
        var payload = parseJsonAttribute(root, "data-chart-payload");

        if (!root || typeof window.Chart === "undefined") {
            return;
        }

        mountChart("wmz-dashboard-coverage-chart", {
            type: "doughnut",
            data: {
                labels: payload.coverage && payload.coverage.labels ? payload.coverage.labels : [],
                datasets: [{
                    data: payload.coverage && payload.coverage.values ? payload.coverage.values : [],
                    backgroundColor: ["#0ea5e9", "#cbd5e1"],
                    hoverBackgroundColor: ["#0284c7", "#94a3b8"],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "68%",
                plugins: {
                    legend: {
                        position: "bottom"
                    }
                }
            }
        });

        mountChart("wmz-dashboard-gaps-chart", {
            type: "bar",
            data: {
                labels: payload.gaps && payload.gaps.labels ? payload.gaps.labels : [],
                datasets: [{
                    label: "Pages",
                    data: payload.gaps && payload.gaps.values ? payload.gaps.values : [],
                    backgroundColor: ["#f59e0b", "#ef4444", "#6366f1"],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });

        mountChart("wmz-dashboard-crawl-chart", {
            type: "line",
            data: {
                labels: payload.crawl && payload.crawl.labels ? payload.crawl.labels : [],
                datasets: [{
                    label: "404 Hits",
                    data: payload.crawl && payload.crawl.values ? payload.crawl.values : [],
                    borderColor: "#dc2626",
                    backgroundColor: "rgba(220,38,38,0.12)",
                    borderWidth: 2,
                    tension: 0.25,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxTicksLimit: 7
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    function buildAnalyticsChart(canvasId, labels, datasets) {
        if (!labels || !labels.length) {
            return;
        }

        mountChart(canvasId, {
            type: "line",
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: "bottom"
                    }
                },
                interaction: {
                    mode: "index",
                    intersect: false
                },
                scales: {
                    x: {
                        ticks: { maxTicksLimit: 8 }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function initAnalyticsCharts() {
        var root = document.getElementById("wmz-analytics-charts");
        var payload = parseJsonAttribute(root, "data-chart-payload");

        if (!root || typeof window.Chart === "undefined") {
            return;
        }

        buildAnalyticsChart("wmz-chart-gsc", payload.gsc && payload.gsc.labels ? payload.gsc.labels : [], [
            {
                label: "Clicks",
                data: payload.gsc && payload.gsc.series && payload.gsc.series.clicks ? payload.gsc.series.clicks : [],
                borderColor: "#0284c7",
                backgroundColor: "rgba(2,132,199,0.12)",
                borderWidth: 2,
                tension: 0.25
            },
            {
                label: "Impressions",
                data: payload.gsc && payload.gsc.series && payload.gsc.series.impressions ? payload.gsc.series.impressions : [],
                borderColor: "#38bdf8",
                backgroundColor: "rgba(56,189,248,0.08)",
                borderWidth: 2,
                tension: 0.25
            }
        ]);

        buildAnalyticsChart("wmz-chart-ga4", payload.ga4 && payload.ga4.labels ? payload.ga4.labels : [], [
            {
                label: "Sessions",
                data: payload.ga4 && payload.ga4.series && payload.ga4.series.sessions ? payload.ga4.series.sessions : [],
                borderColor: "#0ea5e9",
                backgroundColor: "rgba(14,165,233,0.12)",
                borderWidth: 2,
                tension: 0.25
            },
            {
                label: "Pageviews",
                data: payload.ga4 && payload.ga4.series && payload.ga4.series.pageviews ? payload.ga4.series.pageviews : [],
                borderColor: "#1d4ed8",
                backgroundColor: "rgba(29,78,216,0.08)",
                borderWidth: 2,
                tension: 0.25
            }
        ]);

        buildAnalyticsChart("wmz-chart-bing", payload.bing && payload.bing.labels ? payload.bing.labels : [], [
            {
                label: "Clicks",
                data: payload.bing && payload.bing.series && payload.bing.series.clicks ? payload.bing.series.clicks : [],
                borderColor: "#0369a1",
                backgroundColor: "rgba(3,105,161,0.12)",
                borderWidth: 2,
                tension: 0.25
            },
            {
                label: "Impressions",
                data: payload.bing && payload.bing.series && payload.bing.series.impressions ? payload.bing.series.impressions : [],
                borderColor: "#60a5fa",
                backgroundColor: "rgba(96,165,250,0.08)",
                borderWidth: 2,
                tension: 0.25
            }
        ]);
    }

    function getMetaboxConfig() {
        if (window.wpmazicSeoMetabox && typeof window.wpmazicSeoMetabox === "object") {
            return window.wpmazicSeoMetabox;
        }

        return {
            defaultTitle: "",
            defaultDescription: "",
            faqLimit: 0,
            labels: {}
        };
    }

    function initMetabox() {
        if (!$(".wpmazic-metabox-wrap").length) {
            return;
        }

        var config = getMetaboxConfig();
        var labels = config.labels || {};

        function getEditorContent() {
            if (typeof window.tinymce !== "undefined") {
                var editor = window.tinymce.get("content");
                if (editor && !editor.isHidden()) {
                    return editor.getContent({ format: "text" });
                }
            }

            return $("#content").val() || "";
        }

        function updatePreview() {
            var title = ($("#wpmazic_title").val() || "").trim() || config.defaultTitle || "";
            var description = ($("#wpmazic_description").val() || "").trim() || config.defaultDescription || "";

            $("#wpmazic-preview-title").text(title);
            $("#wpmazic-preview-desc").text(description);
        }

        function updateCharCount(inputId, countId, recommended) {
            var value = $("#" + inputId).val() || "";
            var length = value.length;
            var $counter = $("#" + countId);

            if (!$counter.length) {
                return;
            }

            $counter.text(length + " / " + recommended + " " + (labels.characters || "characters"));
            $counter.removeClass("wpmazic-char-warning wpmazic-char-good");

            if (length > recommended) {
                $counter.addClass("wpmazic-char-warning");
            } else if (length >= Math.round(recommended * 0.7) && length > 0) {
                $counter.addClass("wpmazic-char-good");
            }
        }

        function buildFaqRow(item, index) {
            var data = item || {};
            var $row = $('<div class="wpmazic-faq-item-row"></div>');
            var $fields = $('<div class="wpmazic-faq-item-fields"></div>').appendTo($row);

            $('<label class="wpmazic-faq-question-label"></label>')
                .text((labels.question || "Question") + " " + (index + 1))
                .appendTo($fields);

            $('<input type="text" name="wpmazic_faq_question[]">')
                .val(data.question || "")
                .attr("placeholder", labels.enterQuestion || "Enter question")
                .appendTo($fields);

            $('<label class="wpmazic-faq-answer-label" style="margin:8px 0 4px;"></label>')
                .text((labels.answer || "Answer") + " " + (index + 1))
                .appendTo($fields);

            $('<textarea name="wpmazic_faq_answer[]" rows="3"></textarea>')
                .val(data.answer || "")
                .attr("placeholder", labels.enterAnswer || "Enter short answer")
                .appendTo($fields);

            $('<button type="button" class="button-link-delete wpmazic-faq-remove"></button>')
                .attr("aria-label", labels.removeFaq || labels.remove || "Remove")
                .text(labels.remove || "Remove")
                .appendTo($row);

            return $row;
        }

        function refreshFaqRows() {
            var $wrap = $("#wpmazic-faq-items-wrap");
            var limit = parseInt($wrap.attr("data-faq-limit"), 10);

            if (!$wrap.length) {
                return;
            }

            if (isNaN(limit)) {
                limit = parseInt(config.faqLimit, 10) || 0;
            }

            var $rows = $wrap.find(".wpmazic-faq-item-row");
            if (!$rows.length) {
                $wrap.append(buildFaqRow({}, 0));
                $rows = $wrap.find(".wpmazic-faq-item-row");
            }

            $rows.each(function (index) {
                $(this).find(".wpmazic-faq-question-label").text((labels.question || "Question") + " " + (index + 1));
                $(this).find(".wpmazic-faq-answer-label").text((labels.answer || "Answer") + " " + (index + 1));
            });

            var canAdd = limit === 0 || $rows.length < limit;
            $("#wpmazic-faq-add").prop("disabled", !canAdd);
            $rows.find(".wpmazic-faq-remove").show();
            if ($rows.length === 1) {
                $rows.first().find(".wpmazic-faq-remove").hide();
            }
        }

        function updateScore(score, total) {
            var percentage = total > 0 ? Math.round((score / total) * 100) : 0;
            var scoreClass = "wpmazic-score-bad";
            var scoreText = labels.scorePoor || "SEO Score: Poor";

            if (percentage >= 80) {
                scoreClass = "wpmazic-score-good";
                scoreText = labels.scoreGood || "SEO Score: Good";
            } else if (percentage >= 50) {
                scoreClass = "wpmazic-score-ok";
                scoreText = labels.scoreNeedsWork || "SEO Score: Needs Improvement";
            }

            $("#wpmazic-seo-score")
                .removeClass("wpmazic-score-good wpmazic-score-ok wpmazic-score-bad")
                .addClass(scoreClass);

            $("#wpmazic-score-label").text(scoreText + " (" + percentage + "%)");
        }

        function runAnalysis() {
            var $list = $("#wpmazic-analysis-list");
            if (!$list.length) {
                return;
            }

            var keyword = ($("#wpmazic_keyword").val() || "").toLowerCase().trim();
            var title = ($("#wpmazic_title").val() || "").trim();
            var description = ($("#wpmazic_description").val() || "").trim();
            var content = (getEditorContent() || "").trim();
            var contentLower = content.toLowerCase();
            var checks = [];
            var score = 0;

            if (!keyword) {
                $list.html('<li class="wpmazic-check-warn"></li>');
                $list.find("li").text(labels.analysisEmpty || "Enter a focus keyword to see content analysis.");
                $("#wpmazic-seo-score")
                    .removeClass("wpmazic-score-good wpmazic-score-bad")
                    .addClass("wpmazic-score-ok");
                $("#wpmazic-score-label").text(labels.scoreNeedsWork || "SEO Score: Needs Improvement");
                return;
            }

            checks.push({
                ok: title.toLowerCase().indexOf(keyword) !== -1,
                text: labels.checkKeywordInTitle || "Primary keyword appears in the SEO title."
            });
            checks.push({
                ok: description.toLowerCase().indexOf(keyword) !== -1,
                text: labels.checkKeywordInDescription || "Primary keyword appears in the meta description."
            });
            checks.push({
                ok: contentLower.indexOf(keyword) !== -1,
                text: labels.checkKeywordInContent || "Primary keyword appears in the content."
            });
            checks.push({
                ok: title.length >= 30 && title.length <= 60,
                text: labels.checkTitleLength || "SEO title length is in a healthy range."
            });
            checks.push({
                ok: description.length >= 80 && description.length <= 160,
                text: labels.checkDescriptionLength || "Meta description length is in a healthy range."
            });
            checks.push({
                ok: content.split(/\s+/).filter(Boolean).length >= 300,
                text: labels.checkContentLength || "Content length gives search engines enough context."
            });

            $list.empty();
            $.each(checks, function (_, check) {
                var className = check.ok ? "wpmazic-check-good" : "wpmazic-check-warn";
                if (check.ok) {
                    score++;
                }

                $("<li></li>")
                    .addClass(className)
                    .text(check.text)
                    .appendTo($list);
            });

            updateScore(score, checks.length);
        }

        $(document)
            .off("click.wpmazicTabs", ".wpmazic-tab-link")
            .on("click.wpmazicTabs", ".wpmazic-tab-link", function (event) {
                event.preventDefault();

                var tabId = ($(this).data("tab") || "").toString();
                if (!tabId) {
                    return;
                }

                $(".wpmazic-tab-link").removeClass("wpmazic-tab-active");
                $(".wpmazic-tab-content").removeClass("wpmazic-tab-active");

                $(this).addClass("wpmazic-tab-active");
                $("#" + tabId).addClass("wpmazic-tab-active");
            });

        $(document)
            .off("click.wpmazicFaq", "#wpmazic-faq-add")
            .on("click.wpmazicFaq", "#wpmazic-faq-add", function (event) {
                event.preventDefault();

                var $wrap = $("#wpmazic-faq-items-wrap");
                var limit = parseInt($wrap.attr("data-faq-limit"), 10) || 0;
                var count = $wrap.find(".wpmazic-faq-item-row").length;
                if (limit > 0 && count >= limit) {
                    return;
                }

                $wrap.append(buildFaqRow({}, count));
                refreshFaqRows();
            });

        $(document)
            .off("click.wpmazicFaqRemove", ".wpmazic-faq-remove")
            .on("click.wpmazicFaqRemove", ".wpmazic-faq-remove", function (event) {
                event.preventDefault();
                $(this).closest(".wpmazic-faq-item-row").remove();
                refreshFaqRows();
            });

        $(document)
            .off("click.wpmazicMediaUpload", ".wpmazic-upload-image")
            .on("click.wpmazicMediaUpload", ".wpmazic-upload-image", function (event) {
                event.preventDefault();

                if (typeof wp === "undefined" || !wp.media) {
                    return;
                }

                var $button = $(this);
                var targetId = ($button.data("target") || "").toString();
                var previewId = ($button.data("preview") || "").toString();

                var frame = wp.media({
                    title: labels.mediaTitle || "Select or Upload Image",
                    button: {
                        text: labels.mediaButton || "Use this image"
                    },
                    multiple: false
                });

                frame.on("select", function () {
                    var attachment = frame.state().get("selection").first().toJSON();
                    $("#" + targetId).val(attachment.url);
                    $("#" + previewId).attr("src", attachment.url).show();
                    $button.siblings(".wpmazic-remove-image").show();
                });

                frame.open();
            });

        $(document)
            .off("click.wpmazicMediaRemove", ".wpmazic-remove-image")
            .on("click.wpmazicMediaRemove", ".wpmazic-remove-image", function (event) {
                event.preventDefault();

                var targetId = ($(this).data("target") || "").toString();
                var previewId = ($(this).data("preview") || "").toString();

                $("#" + targetId).val("");
                $("#" + previewId).attr("src", "").hide();
                $(this).hide();
            });

        $("#wpmazic_title, #wpmazic_description, #wpmazic_keyword, #content")
            .off(".wpmazicMetabox")
            .on("input.wpmazicMetabox keyup.wpmazicMetabox", function () {
                updateCharCount("wpmazic_title", "wpmazic-title-count", 60);
                updateCharCount("wpmazic_description", "wpmazic-desc-count", 160);
                updatePreview();
                runAnalysis();
            });

        $(document)
            .off("tinymce-editor-init.wpmazicMetabox")
            .on("tinymce-editor-init.wpmazicMetabox", function (_event, editor) {
                if (!editor || editor.id !== "content") {
                    return;
                }

                editor.on("keyup change", function () {
                    runAnalysis();
                });
            });

        updateCharCount("wpmazic_title", "wpmazic-title-count", 60);
        updateCharCount("wpmazic_description", "wpmazic-desc-count", 160);
        updatePreview();
        refreshFaqRows();
        runAnalysis();
    }

    $(document).ready(function () {
        $(".wpmazic-seo-admin .wmz-input, .wpmazic-seo-admin .wmz-select, .wpmazic-seo-admin .wmz-textarea").on(
            "input change",
            function () {
                markDirtyField($(this));
            }
        );

        $(".wpmazic-seo-admin .wmz-table-wrap").each(function () {
            attachTableSearch($(this));
        });

        relocateGlobalAdminNotices();

        $(document).on("click", "[data-wmz-group-toggle]", function () {
            toggleShellGroup($(this));
        });

        $(document).on("click", ".wpmazic-seo-admin .wmz-shell-save-trigger", function (e) {
            var $target = findPrimarySaveButton();
            if (!$target.length) {
                e.preventDefault();
                return;
            }

            var top = Math.max(0, $target.offset().top - 120);
            $("html, body").animate({ scrollTop: top }, 180);
            $target.trigger("focus");
        });

        syncShellSaveTrigger();
        initDashboardCharts();
        initAnalyticsCharts();
        initMetabox();

        $(".wpmazic-seo-admin button").on("click", function (e) {
            var text = ($(this).text() || "").toLowerCase();
            if (text.indexOf("delete") === -1 && text.indexOf("clear") === -1 && text.indexOf("truncate") === -1) {
                return;
            }

            if (!window.confirm("Are you sure you want to continue?")) {
                e.preventDefault();
            }
        });
    });
})(jQuery);
