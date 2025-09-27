jQuery(document).ready(function ($) {
  "use strict";

  // Global variables
  var testingConnection = false;
  var autoRefreshInterval;

  // Initialize admin functionality
  initializeAdmin();

  function initializeAdmin() {
    setupConnectionTests();
    setupQueueManagement();
    setupLogsManagement();
    setupAutoRefresh();
    setupFormValidation();
    setupTooltips();
  }

  // Connection Testing
  function setupConnectionTests() {
    // Test Reddit connection
    $("#test-reddit-connection").on("click", function () {
      if (testingConnection) return;

      var $button = $(this);
      var $result = $("#reddit-test-result");

      // Validate required fields
      var clientId = $("#reddit_client_id").val().trim();
      var clientSecret = $("#reddit_client_secret").val().trim();
      var username = $("#reddit_username").val().trim();
      var password = $("#reddit_password").val().trim();

      if (!clientId || !clientSecret || !username || !password) {
        showTestResult(
          $result,
          "error",
          "Please fill in all Reddit credentials first."
        );
        return;
      }

      testingConnection = true;
      $button.prop("disabled", true).text("Testing...");
      showTestResult($result, "loading", "Testing connection...");

      $.ajax({
        url: reddit_bot_ajax.ajax_url,
        type: "POST",
        data: {
          action: "reddit_bot_test_connection",
          nonce: reddit_bot_ajax.nonce,
          client_id: clientId,
          client_secret: clientSecret,
          username: username,
          password: password,
        },
        timeout: 30000,
        success: function (response) {
          if (response.success) {
            showTestResult($result, "success", "Connection successful! ✓");
          } else {
            showTestResult(
              $result,
              "error",
              "Connection failed: " + response.message
            );
          }
        },
        error: function (xhr, status, error) {
          var message = "Connection test failed";
          if (status === "timeout") {
            message = "Connection test timed out";
          } else if (xhr.responseJSON && xhr.responseJSON.data) {
            message = xhr.responseJSON.data;
          }
          showTestResult($result, "error", message);
        },
        complete: function () {
          testingConnection = false;
          $button.prop("disabled", false).text("Test Reddit Connection");
        },
      });
    });

    // Test AI connection
    $("#test-ai-connection").on("click", function () {
      if (testingConnection) return;

      var $button = $(this);
      var $result = $("#ai-test-result");

      var apiKey = $("#openrouter_api_key").val().trim();

      if (!apiKey) {
        showTestResult(
          $result,
          "error",
          "Please enter your OpenRouter API key first."
        );
        return;
      }

      testingConnection = true;
      $button.prop("disabled", true).text("Testing...");
      showTestResult($result, "loading", "Testing AI connection...");

      $.ajax({
        url: reddit_bot_ajax.ajax_url,
        type: "POST",
        data: {
          action: "reddit_bot_test_ai",
          nonce: reddit_bot_ajax.nonce,
          api_key: apiKey,
        },
        timeout: 30000,
        success: function (response) {
          if (response.success) {
            showTestResult($result, "success", "AI connection successful! ✓");
          } else {
            showTestResult(
              $result,
              "error",
              "AI connection failed: " + response.message
            );
          }
        },
        error: function (xhr, status, error) {
          var message = "AI test failed";
          if (status === "timeout") {
            message = "AI test timed out";
          }
          showTestResult($result, "error", message);
        },
        complete: function () {
          testingConnection = false;
          $button.prop("disabled", false).text("Test AI Connection");
        },
      });
    });
  }

  function showTestResult($element, type, message) {
    $element
      .removeClass("test-success test-error test-loading")
      .addClass("test-" + type)
      .text(message);

    if (type === "success" || type === "error") {
      setTimeout(function () {
        $element.fadeOut(300, function () {
          $(this)
            .text("")
            .removeClass("test-success test-error test-loading")
            .show();
        });
      }, 5000);
    }
  }

  // Queue Management
  function setupQueueManagement() {
    // Force queue processing
    $(document).on("click", "#force-queue-run", function () {
      var $button = $(this);

      if (
        !confirm(
          "Force process the comment queue now? This will attempt to post pending comments immediately."
        )
      ) {
        return;
      }

      $button.prop("disabled", true).text("Processing...");

      $.ajax({
        url: reddit_bot_ajax.ajax_url,
        type: "POST",
        data: {
          action: "reddit_bot_force_queue",
          nonce: reddit_bot_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            showNotification(
              "Queue processing started. Check logs for results.",
              "success"
            );
            setTimeout(function () {
              location.reload();
            }, 2000);
          } else {
            showNotification("Error: " + response.data, "error");
          }
        },
        error: function () {
          showNotification("Failed to start queue processing.", "error");
        },
        complete: function () {
          $button.prop("disabled", false).text("Force Queue Processing");
        },
      });
    });

    // Delete queue item
    $(document).on("click", ".delete-queue-item", function () {
      var $button = $(this);
      var id = $button.data("id");
      var $row = $button.closest("tr");

      if (!confirm("Delete this queue item?")) {
        return;
      }

      $button.prop("disabled", true);

      $.ajax({
        url: reddit_bot_ajax.ajax_url,
        type: "POST",
        data: {
          action: "reddit_bot_delete_queue_item",
          id: id,
          nonce: reddit_bot_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            $row.fadeOut(300, function () {
              $(this).remove();
              updateQueueCounts();
            });
            showNotification("Queue item deleted.", "success");
          } else {
            showNotification("Error: " + response.data, "error");
            $button.prop("disabled", false);
          }
        },
        error: function () {
          showNotification("Failed to delete queue item.", "error");
          $button.prop("disabled", false);
        },
      });
    });

    // Retry queue item
    $(document).on("click", ".retry-queue-item", function () {
      var $button = $(this);
      var id = $button.data("id");
      var $row = $button.closest("tr");

      $button.prop("disabled", true).text("Retrying...");

      $.ajax({
        url: reddit_bot_ajax.ajax_url,
        type: "POST",
        data: {
          action: "reddit_bot_retry_queue_item",
          id: id,
          nonce: reddit_bot_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            $row.removeClass("status-failed").addClass("status-pending");
            $row
              .find(".status-badge")
              .removeClass("status-failed")
              .addClass("status-pending")
              .text("Pending");
            $button
              .closest(".column-actions")
              .html(
                '<button type="button" class="button-link delete-queue-item" data-id="' +
                  id +
                  '">Remove</button>'
              );
            showNotification("Queue item marked for retry.", "success");
          } else {
            showNotification("Error: " + response.data, "error");
            $button.prop("disabled", false).text("Retry");
          }
        },
        error: function () {
          showNotification("Failed to retry queue item.", "error");
          $button.prop("disabled", false).text("Retry");
        },
      });
    });

    // View full comment modal
    $(document).on("click", ".view-full-comment", function () {
      var comment = $(this).data("comment");
      $("#modal-comment-text").text(comment);
      $("#comment-modal").fadeIn(200);
    });

    // Close modal
    $(document).on("click", ".modal-close, #comment-modal", function (e) {
      if (e.target === this) {
        $("#comment-modal").fadeOut(200);
      }
    });

    // Escape key closes modal
    $(document).on("keydown", function (e) {
      if (e.keyCode === 27) {
        // Escape key
        $("#comment-modal").fadeOut(200);
      }
    });
  }

  function updateQueueCounts() {
    // Update queue statistics after item removal
    var $table = $(".queue-item").closest("table");
    var pending = $table.find(".status-pending").length;
    var posted = $table.find(".status-posted").length;
    var failed = $table.find(".status-failed").length;
    var total = pending + posted + failed;

    $(".stat-box:eq(0) h3").text(total);
    $(".stat-box:eq(1) h3").text(pending);
    $(".stat-box:eq(2) h3").text(posted);
    $(".stat-box:eq(3) h3").text(failed);
  }

  // Logs Management
  function setupLogsManagement() {
    // Clear old logs
    $(document).on("click", "#clear-logs", function () {
      var $button = $(this);

      if (!confirm("Clear all logs older than 30 days?")) {
        return;
      }

      $button.prop("disabled", true).text("Clearing...");

      $.ajax({
        url: reddit_bot_ajax.ajax_url,
        type: "POST",
        data: {
          action: "reddit_bot_clear_logs",
          nonce: reddit_bot_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            showNotification("Old logs cleared successfully.", "success");
            setTimeout(function () {
              location.reload();
            }, 1000);
          } else {
            showNotification("Error: " + response.data, "error");
          }
        },
        error: function () {
          showNotification("Failed to clear logs.", "error");
        },
        complete: function () {
          $button.prop("disabled", false).text("Clear Old Logs (30+ days)");
        },
      });
    });

    // Highlight recent errors
    highlightRecentErrors();
  }

  function highlightRecentErrors() {
    $(".log-level-error").each(function () {
      var $row = $(this);
      var logTime = $row.find(".column-time span").attr("title");

      if (logTime) {
        var logDate = new Date(logTime);
        var now = new Date();
        var diffMinutes = (now - logDate) / (1000 * 60);

        // Highlight errors from last 30 minutes
        if (diffMinutes < 30) {
          $row.addClass("recent-error");
        }
      }
    });
  }

  // Auto Refresh
  function setupAutoRefresh() {
    // Auto-refresh dashboard every 30 seconds
    if (
      window.location.href.indexOf("page=reddit-bot") > -1 &&
      window.location.href.indexOf("settings") === -1
    ) {
      autoRefreshInterval = setInterval(function () {
        refreshDashboardStats();
      }, 30000);
    }

    // Auto-refresh logs every 60 seconds
    if (window.location.href.indexOf("reddit-bot-logs") > -1) {
      autoRefreshInterval = setInterval(function () {
        // Only auto-refresh if no filters are applied
        var urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.get("level") && !urlParams.get("action")) {
          refreshLogsTable();
        }
      }, 60000);
    }

    // Manual refresh buttons
    $(document).on("click", "#refresh-queue, #refresh-logs", function () {
      location.reload();
    });
  }

  function refreshDashboardStats() {
    $.ajax({
      url: reddit_bot_ajax.ajax_url,
      type: "POST",
      data: {
        action: "reddit_bot_get_stats",
        nonce: reddit_bot_ajax.nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          updateDashboardStats(response.data);
        }
      },
      error: function () {
        // Silently fail for auto-refresh
      },
    });
  }

  function updateDashboardStats(stats) {
    // Update bot status
    var $statusCard = $(".status-card").first();
    $statusCard
      .removeClass("enabled disabled")
      .addClass(stats.bot_enabled ? "enabled" : "disabled");
    $statusCard
      .find(".status-text")
      .text(stats.bot_enabled ? "ENABLED" : "DISABLED");

    // Update queue numbers
    $(".status-card").eq(1).find(".status-number").text(stats.queue_pending);
    $(".status-card")
      .eq(1)
      .find("small")
      .html(
        "Total: " +
          stats.queue_total +
          " | " +
          "Posted: " +
          stats.queue_posted +
          " | " +
          "Failed: " +
          stats.queue_failed
      );

    // Update AI usage
    $(".status-card").eq(2).find(".status-number").text(stats.ai_generated_24h);
    $(".status-card")
      .eq(2)
      .find("small")
      .text("Est. Cost: $" + stats.estimated_cost_24h);
  }

  function refreshLogsTable() {
    var $logsTable = $(".logs-table");
    var $originalContent = $logsTable.html();

    $.ajax({
      url: reddit_bot_ajax.ajax_url,
      type: "POST",
      data: {
        action: "reddit_bot_get_recent_logs",
        nonce: reddit_bot_ajax.nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          $logsTable.html(response.data);
          highlightRecentErrors();
        }
      },
      error: function () {
        // Restore original content on error
        $logsTable.html($originalContent);
      },
    });
  }

  // Form Validation
  function setupFormValidation() {
    // Settings form validation
    $("form").on("submit", function (e) {
      var isValid = true;
      var errors = [];

      // Check required fields based on bot enabled state
      if ($("#bot_enabled").is(":checked")) {
        // Reddit credentials required
        if (!$("#reddit_client_id").val().trim()) {
          errors.push("Reddit Client ID is required when bot is enabled.");
          isValid = false;
        }
        if (!$("#reddit_client_secret").val().trim()) {
          errors.push("Reddit Client Secret is required when bot is enabled.");
          isValid = false;
        }
        if (!$("#reddit_username").val().trim()) {
          errors.push("Reddit Username is required when bot is enabled.");
          isValid = false;
        }
        if (!$("#reddit_password").val().trim()) {
          errors.push("Reddit Password is required when bot is enabled.");
          isValid = false;
        }

        // OpenRouter API key required
        if (!$("#openrouter_api_key").val().trim()) {
          errors.push("OpenRouter API Key is required when bot is enabled.");
          isValid = false;
        }

        // Target subreddits required
        if (!$("#target_subreddits").val().trim()) {
          errors.push("Target Subreddits are required when bot is enabled.");
          isValid = false;
        }

        // Website URL required
        if (!$("#website_url").val().trim()) {
          errors.push("Website URL is required when bot is enabled.");
          isValid = false;
        }
      }

      // Validate rate limit
      var rateLimit = parseInt($("#max_comments_per_hour").val());
      if (isNaN(rateLimit) || rateLimit < 1 || rateLimit > 20) {
        errors.push("Rate limit must be between 1 and 20 comments per hour.");
        isValid = false;
      }

      if (!isValid) {
        e.preventDefault();
        showNotification(
          "Please fix the following errors:\n• " + errors.join("\n• "),
          "error"
        );
        return false;
      }
    });

    // Real-time validation feedback
    $("#bot_enabled")
      .on("change", function () {
        var $requiredFields = $(
          "#reddit_client_id, #reddit_client_secret, #reddit_username, #reddit_password, #openrouter_api_key"
        );

        if ($(this).is(":checked")) {
          $requiredFields.attr("required", true);
          $(".form-table tr").has($requiredFields).addClass("required-field");
        } else {
          $requiredFields.removeAttr("required");
          $(".form-table tr").removeClass("required-field");
        }
      })
      .trigger("change");

    // Subreddit validation
    $("#target_subreddits").on("blur", function () {
      var subreddits = $(this).val().trim();
      if (subreddits) {
        var subredditList = subreddits.split(",").map((s) => s.trim());
        var validSubreddits = subredditList.filter((s) =>
          /^[a-zA-Z0-9_]+$/.test(s)
        );

        if (validSubreddits.length !== subredditList.length) {
          showNotification(
            "Some subreddit names contain invalid characters. Use only letters, numbers, and underscores.",
            "warning"
          );
        }
      }
    });
  }

  // Tooltips and Help
  function setupTooltips() {
    // Add tooltips to various elements
    $("[title]").tooltip({
      position: { my: "left top+15", at: "left bottom", collision: "flipfit" },
    });

    // Expandable help sections
    $(".help-toggle").on("click", function () {
      $(this).next(".help-content").slideToggle(200);
      $(this)
        .find(".dashicons")
        .toggleClass("dashicons-arrow-down dashicons-arrow-up");
    });
  }

  // Utility Functions
  function showNotification(message, type) {
    type = type || "info";

    // Remove existing notifications
    $(".reddit-bot-notification").remove();

    var $notification = $(
      '<div class="reddit-bot-notification notice notice-' +
        type +
        ' is-dismissible">' +
        "<p>" +
        message.replace(/\n/g, "<br>") +
        "</p>" +
        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
        "</div>"
    );

    $(".wrap h1").after($notification);

    // Auto-dismiss after 5 seconds for success messages
    if (type === "success") {
      setTimeout(function () {
        $notification.fadeOut(300, function () {
          $(this).remove();
        });
      }, 5000);
    }

    // Handle dismiss button
    $notification.find(".notice-dismiss").on("click", function () {
      $notification.fadeOut(300, function () {
        $(this).remove();
      });
    });

    // Scroll to notification
    $("html, body").animate(
      {
        scrollTop: $notification.offset().top - 50,
      },
      300
    );
  }

  function debounce(func, wait) {
    var timeout;
    return function executedFunction() {
      var context = this;
      var args = arguments;
      var later = function () {
        timeout = null;
        func.apply(context, args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  // Cleanup on page unload
  $(window).on("beforeunload", function () {
    if (autoRefreshInterval) {
      clearInterval(autoRefreshInterval);
    }
  });

  // Initialize keyboard shortcuts
  $(document).on("keydown", function (e) {
    // Ctrl/Cmd + R for refresh (prevent default and use our refresh)
    if ((e.ctrlKey || e.metaKey) && e.keyCode === 82) {
      if (window.location.href.indexOf("reddit-bot") > -1) {
        e.preventDefault();
        location.reload();
      }
    }

    // Ctrl/Cmd + S for save settings
    if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
      if (window.location.href.indexOf("reddit-bot-settings") > -1) {
        e.preventDefault();
        $("form").submit();
      }
    }
  });

  // Console logging for debugging
  if (typeof console !== "undefined" && console.log) {
    console.log("Reddit Bot Admin JS loaded successfully");
  }
});
