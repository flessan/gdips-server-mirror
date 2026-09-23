/* ==========================================================================
   GDIPS UI - front-end behavior
   --------------------------------------------------------------------------
   One small dependency-free script that powers every dashboard page:

   - gdBoot()        (re)binds the shell (audio player, drawer) after each
					 SPA navigation. Idempotent: safe to call repeatedly.
   - a(...)          SPA navigation used by pages via onclick="a('...')".
					 Same signature as the legacy inline implementation so
					 existing pages keep working:
					 a(page, skipcheck, skipslash, method, getdata, formname, isback)
   - createToast()   toast notifications (#error-divs / .notify)
   - btnsong(), likeSong(), deleteSong(), disableSong(), renameSong(),
	 copysong(), downloadLevel(), cron(), escapeHtml()
   - player          the floating audio player (queue, covers, media keys)

   Localized strings and per-install settings arrive through window.GDIPS,
   printed by dashboardLib::printNavbar(). No frameworks, no build step.
   ========================================================================== */
"use strict";

/* ------------------------------------------------------------------ *
 *  Delegated document handlers - bound exactly once                  *
 * ------------------------------------------------------------------ */
function gdBindOnce() {
	if (window.__gdipsBound) return;
	window.__gdipsBound = true;

	/* Keep legacy behavior: plain <a class="dropdown-item"> elements are
	   triggered through onclick="a()" - don't follow their href. Elements
	   marked .dontblock are real links and stay clickable. */
	document.addEventListener("click", function (e) {
		if (!e.target || typeof e.target.classList !== "object") return;
		if (e.target.closest(".dontblock")) return;
		if (e.target.closest(".dropdown-item") || e.target.closest(".nav-link")) {
			if (e.target.tagName === "A") e.preventDefault();
			return;
		}
		if (e.target.closest("i.fa-solid, i.fa-regular, i.fa-brands") &&
			e.target.closest(".dropdown-toggle")) e.preventDefault();
	});

	/* Enable the shell login button only when both fields have content. */
	document.addEventListener("input", function () {
		var u = document.getElementById("usernameField");
		var p = document.getElementById("passwordField");
		var b = document.getElementById("submit");
		if (!u || !p || !b) return;
		if (!u.value.trim().length || !p.value.trim().length) b.setAttribute("disabled", "");
		else b.removeAttribute("disabled");
	});

	/* Close the mobile drawer on Escape. */
	document.addEventListener("keydown", function (e) {
		if (e.key === "Escape") gdCloseDrawer();
	});

	window.addEventListener("popstate", function () {
		a(location.href, true, true, "GET", false, "", true);
	});
}

/* ------------------------------------------------------------------ *
 *  Mobile drawer                                                     *
 * ------------------------------------------------------------------ */
function gdToggleDrawer() {
	document.body.classList.toggle("gd-drawer-open");
	var b = document.querySelector(".gd-burger");
	if (b) b.setAttribute("aria-expanded", document.body.classList.contains("gd-drawer-open") ? "true" : "false");
}
function gdCloseDrawer() {
	document.body.classList.remove("gd-drawer-open");
	var b = document.querySelector(".gd-burger");
	if (b) b.setAttribute("aria-expanded", "false");
}

function gdSetTheme(theme) {
	document.documentElement.dataset.theme = theme;
	localStorage.setItem("gdips-theme", theme);
}

function gdToggleTheme() {
	var current = document.documentElement.dataset.theme || "dark";
	gdSetTheme(current === "dark" ? "light" : "dark");
}

function gdInitTheme() {
	var saved = localStorage.getItem("gdips-theme");
	var theme = saved || "dark";
	gdSetTheme(theme);
}

/* ------------------------------------------------------------------ *
 *  Toasts                                                            *
 * ------------------------------------------------------------------ */
function createToast(text) {
	var host = document.querySelector("#error-divs");
	if (!host) return;
	var toast = document.createElement("div");
	toast.className = "notify";
	toast.innerHTML = text;
	host.append(toast);
	setTimeout(function () { toast.classList.add("notify-show"); }, 60);
	setTimeout(function () {
		toast.classList.remove("notify-show");
		setTimeout(function () { toast.remove(); }, 350);
	}, 3400);
}

/* ------------------------------------------------------------------ *
 *  SPA navigation - a()                                              *
 *  Fetches a dashboard page, swaps #htmlpage + #navbarepta, keeps    *
 *  history in sync. getdata > 0 collects query fields from one of    *
 *  the page's forms (indexed from the end; 69 = form[name=searchform]). *
 * ------------------------------------------------------------------ */
function a(page, skipcheck, skipslash, method, getdata, formname, isback) {
	try {
		method = method || "GET";
		getdata = (getdata === undefined || getdata === null) ? false : getdata;
		page = (page === undefined || page === null) ? "" : String(page);

		if (!skipslash && page !== "" && page.slice(-4) !== ".php" && page.slice(-1) !== "/" && page.indexOf("?") === -1) page += "/";
		if (!skipslash && page.slice(-4) === ".php/") page = page.slice(0, -1);
		if (page === "/") page = "";

		/* Don't re-fetch the page we are already on. */
		if (!skipcheck && page !== "") {
			var probe = new URL(page, document.baseURI);
			if (probe.pathname === location.pathname && (probe.search === location.search || page.indexOf("?") !== -1)) {
				if (probe.pathname === location.pathname) return;
			}
		}

		var parts = page.split("?");
		page = parts[0];
		var inlineQuery = "";
		if (typeof parts[1] !== "undefined") inlineQuery = "?" + parts[1];

		var sendget = "";
		if (getdata > 0 || getdata === 69) {
			var fd;
			if (getdata !== 69) fd = new FormData(document.getElementsByTagName("form")[document.getElementsByTagName("form").length - getdata]);
			else fd = new FormData(document.getElementsByName("searchform")[0]);
			var delim = "?";
			["search", "type", "who", "ng", "levelID", "page"].forEach(function (field) {
				if (fd.get(field) !== null) {
					sendget += delim + field + "=" + encodeURIComponent(fd.get(field));
					delim = "&";
				}
			});
		} else {
			sendget = inlineQuery;
		}

		var orb = document.getElementById("loadingloool");
		if (orb) { orb.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; orb.style.opacity = "1"; }

		var pg = new XMLHttpRequest();
		pg.open(method, page + sendget, true);
		pg.responseType = "document";

		pg.onload = function () {
			/* Upload progress reset (kept id: #progress). */
			var prog = document.getElementById("progress");
			if (prog) { prog.value = "0"; prog.style.display = "none"; }

			if (!pg.response || pg.response.getElementById("htmlpage") === null) {
				if (orb) {
					orb.innerHTML = '<i class="fa-solid fa-xmark" style="color:#ff8d90"></i>';
					setTimeout(function () { orb.style.opacity = "0"; }, 900);
				}
				return;
			}
			if (orb) orb.style.opacity = "0";

			var errText = pg.response.getElementById("dashboard-error-text");
			if (errText !== null) { createToast(errText.innerHTML); return; }

			/* Swap <title>, shell and content. */
			var newTitle = pg.response.querySelectorAll("title")[0];
			var curTitle = document.querySelectorAll("title")[0];
			if (newTitle && curTitle) curTitle.replaceWith(newTitle);

			var newNav = pg.response.querySelector("#navbarepta");
			var curNav = document.querySelector("#navbarepta");
			if (newNav && curNav) curNav.replaceWith(newNav);

			var newContent = pg.response.querySelector("#htmlpage");
			var curContent = document.querySelector("#htmlpage");
			if (newContent && curContent) curContent.replaceWith(newContent);

			/* Re-run the page's own inline script (last <script> in body),
			   plus the pagination script (#bottomrowscript). */
			var scripts = pg.response.querySelectorAll("body script");
			var pageScript = scripts[scripts.length - 1];
			var bottomScript = pg.response.getElementById("bottomrowscript");
			var scrp = document.createElement("script");
			scrp.id = "pagescript";
			if (pageScript && typeof pageScript.textContent !== "undefined") scrp.innerHTML = pageScript.textContent;
			if (bottomScript) scrp.innerHTML += bottomScript.textContent;
			var oldScrp = document.getElementById("pagescript");
			if (oldScrp) oldScrp.remove();
			document.body.appendChild(scrp);

			/* Keep <base> pointing at the dashboard root for the new depth. */
			var sub = document.getElementById("isSubdirectory");
			var base = document.querySelector("base");
			if (base) base.setAttribute("href", "/dashboard/");

			/* History entry (absolute path resolved against the old base). */
			if (!isback) {
				var resolved = new URL(page + sendget, document.baseURI);
				if (page === "") resolved = new URL(document.baseURI);
				history.pushState(null, "", resolved.pathname + resolved.search + resolved.hash);
			}

			/* Close the drawer, rebind the shell, scroll up. */
			gdCloseDrawer();
			gdBoot();
			window.scrollTo(0, 0);
		};

		pg.onerror = function () {
			if (orb) orb.style.opacity = "0";
			createToast("Connection error");
		};

		if (typeof prog !== "undefined" && prog) {
			pg.upload.onprogress = function (event) {
				prog.max = event.total;
				prog.style.display = "block";
				prog.value = event.loaded;
			};
		}

		if (method === "POST") {
			var postFd = (formname === "" || formname === undefined)
				? new FormData(document.getElementsByTagName("form")[document.getElementsByTagName("form").length - 1])
				: new FormData(document.getElementsByName(formname)[0]);
			pg.send(postFd);
		} else {
			pg.send(sendget);
		}
	} catch (e) {
		console.log(e);
	}
}

/* ------------------------------------------------------------------ *
 *  Small utilities                                                   *
 * ------------------------------------------------------------------ */
function escapeHtml(text) {
	var map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#039;" };
	return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
}

function copysong(id) {
	if (navigator.clipboard) navigator.clipboard.writeText(id);
	var el = document.getElementById("copy" + id);
	if (!el) return;
	el.style.transition = "0.05s";
	el.style.color = "#7bd7a8";
	setTimeout(function () { el.style.transition = "0.2s"; }, 50);
	setTimeout(function () { el.style.color = ""; }, 700);
}

function downloadLevel(levelID) {
	var icon = document.getElementById("levelDownloadIcon" + levelID);
	if (icon) icon.className = "fa-solid fa-spinner fa-spin";
	fetch("api/getGMD.php", {
		method: "POST",
		body: "levelID=" + levelID,
		headers: { "Content-Type": "application/x-www-form-urlencoded" }
	}).then(function (r) { return r.json(); }).then(function (result) {
		if (result.success) {
			var fakeA = document.createElement("a");
			fakeA.href = "data:text/xml;base64," + result.GMD;
			fakeA.download = result.levelName + ".gmd";
			fakeA.click();
		} else createToast("Download failed");
		if (icon) icon.className = "fa-solid fa-download";
	}).catch(function () { if (icon) icon.className = "fa-solid fa-download"; });
}

function cron() {
	var iconCron = document.getElementById("iconcron");
	var cronButton = document.getElementById("crbtn");
	var i18n = (window.GDIPS && window.GDIPS.i18n) || {};
	if (iconCron) iconCron.className = "fa-solid fa-spinner fa-spin";
	fetch("api/runCron.php").then(function (r) { return r.json(); }).then(function (response) {
		if (iconCron) iconCron.className = "fa-solid fa-bars-progress";
		if (response.success) {
			if (cronButton) {
				cronButton.innerHTML = '<i id="iconcron" class="fa-solid fa-check gd-nav-link-check"></i><span>' + (i18n.cronSuccess || "Success!") + "</span>";
				cronButton.classList.add("dropdown-success");
				cronButton.disabled = true;
			}
		} else if (cronButton) {
			cronButton.innerHTML = '<i id="iconcron" class="fa-solid fa-xmark"></i><span>' + (i18n.cronError || "Error!") + "</span>";
			cronButton.classList.add("dropdown-error");
		}
	});
}

/* ------------------------------------------------------------------ *
 *  Song actions (favourite / rename / delete / disable)              *
 * ------------------------------------------------------------------ */
function likeSong(id) {
	var likebtn = document.getElementById("like" + id);
	var likeicon = document.getElementById("likeicon" + id);
	var i18n = (window.GDIPS && window.GDIPS.i18n) || {};
	if (!likebtn || !likeicon) return;
	var fav = likebtn.value == "1";
	likeicon.classList.toggle("fa-regular", fav);
	likeicon.classList.toggle("fa-solid", !fav);
	likebtn.value = fav ? "0" : "1";
	likebtn.title = fav ? (i18n.likeSong || "Add to favourites") : (i18n.dislikeSong || "Remove from favourites");
	var req = new XMLHttpRequest();
	req.open("GET", "stats/favourite.php?id=" + id, true);
	req.onload = function () {
		if (req.response === "-1") { /* revert on failure */
			likeicon.classList.toggle("fa-regular", !fav);
			likeicon.classList.toggle("fa-solid", fav);
			likebtn.value = fav ? "1" : "0";
		}
	};
	req.send();
}

function deleteSong(id, isSFX) {
	var del = new XMLHttpRequest();
	del.open("GET", "stats/deleteSong.php?ID=" + id + (isSFX ? "&sfx" : ""), true);
	del.onload = function () {
		try {
			if (JSON.parse(del.response).success) {
				var card = document.getElementById("songCard" + id);
				if (card) card.remove();
			}
		} catch (e) { console.log(e); }
	};
	del.send();
}

function disableSong(id, isSFX) {
	var del = new XMLHttpRequest();
	var i18n = (window.GDIPS && window.GDIPS.i18n) || {};
	del.open("GET", "stats/deleteSong.php?ID=" + id + "&disable" + (isSFX ? "&sfx" : ""), true);
	del.onload = function () {
		try {
			if (!JSON.parse(del.response).success) return;
			var iconDiv = document.getElementById("songDisableIcon" + id);
			var textDiv = document.getElementById("songDisableText" + id);
			if (!iconDiv || !textDiv) return;
			var isDisabled = textDiv.innerHTML === (i18n.songIsAvailable || "Available");
			iconDiv.className = "fa-solid fa-" + (isDisabled ? "xmark" : "check");
			textDiv.innerHTML = isDisabled ? (i18n.songIsDisabled || "Disabled") : (i18n.songIsAvailable || "Available");
		} catch (e) { console.log(e); }
	};
	del.send();
}

function renameSong(id, isSFX) {
	var form = document.getElementsByName("songrename" + id)[0];
	if (!form) return;
	var nfd = new FormData(form);
	var ren = new XMLHttpRequest();
	ren.open("POST", "stats/renameSong.php", true);
	ren.onload = function () {
		try {
			if (JSON.parse(ren.response).success) {
				var el = document.getElementById("songname" + id);
				if (el) el.innerHTML = isSFX ? nfd.get("name") : nfd.get("author") + " - " + nfd.get("name");
			}
		} catch (e) { console.log(e); }
	};
	ren.send(nfd);
}

/* ------------------------------------------------------------------ *
 *  Audio player                                                      *
 * ------------------------------------------------------------------ */
var player = null;

function gdInitPlayer() {
	var root = document.getElementById("audioPlayer");
	if (!root) return;

	player = root;
	player.showButton = document.getElementById("audioPlayerButton");
	player.isPlaying = false;
	player.queue = [];
	player.queueDiv = document.getElementById("audioQueue");
	player.covers = {};
	player.number = -1;
	player.button = document.getElementById("audioButton");
	player.buttonBackward = document.getElementById("audioBackward");
	player.buttonForward = document.getElementById("audioForward");
	player.cover = document.getElementById("audioImage");
	player.name = document.getElementById("audioName");
	player.author = document.getElementById("audioAuthor");
	player.progress = document.getElementById("audioProgress");
	player.volume = document.getElementById("audioVolume");
	player.volumeDiv = document.getElementById("audioAnotherVolume");
	player.volumeIcon = document.getElementById("audioVolumeIcon");

	if (!window.localStorage.volume) window.localStorage.volume = 0.2;

	player.volume.change = function (value, directly) {
		var v = directly ? value : value.target.value;
		player.song.volume = window.localStorage.volume = v / 1000;
		player.progress.style.backgroundSize = (v / 1000 * 100) + "% 100%, 100% 100%";
	};

	/* Show/hide choreography */
	player.showButton.addEventListener("mouseenter", function () {
		player.showButton.classList.add("show");
		root.classList.add("show");
	});
	root.addEventListener("mouseleave", function () {
		player.showButton.classList.remove("show");
		root.classList.remove("show");
	});
	player.volumeIcon.addEventListener("mouseenter", function () {
		player.volumeDiv.classList.add("show");
		player.volumeIcon.classList.remove("show");
	});
	player.volumeDiv.addEventListener("mouseleave", function () {
		player.volumeDiv.classList.remove("show");
		player.volumeIcon.classList.add("show");
	});
	document.addEventListener("click", function (e) {
		if (e.target.id === "audioPlayerButton" || e.target.id === "audioPlayerButtonI") return;
		if (e.target.closest && e.target.closest("#audioPlayer, #audioPlayerButton, .audioDiv .item")) return;
		player.showButton.classList.remove("show");
		root.classList.remove("show");
		player.queueDiv.classList.remove("show");
	});

	/* Queue button (long-press/toggle) */
	player.showButton.addEventListener("dblclick", function () {
		player.queueDiv.classList.toggle("show");
	});

	player.progress.update = function (e) {
		var percent = e.target.valueAsNumber / player.progress.max * 100;
		player.progress.style.backgroundSize = (percent > 50 ? percent * 0.98 : percent * 1.02) + "% 100%, 100% 100%";
	};
	player.progress.addEventListener("change", player.progress.update);
	player.progress.addEventListener("input", function (e) {
		player.song.currentTime = e.target.value;
		player.progress.update(e);
	});
	player.volume.addEventListener("input", player.volume.change);
	player.volume.value = window.localStorage.volume * 1000;

	player.song.addEventListener("timeupdate", function (e) {
		player.progress.value = e.target.currentTime;
		player.progress.max = player.song.duration || 0;
		player.progress.update({ target: player.progress });
	});
	player.song.addEventListener("ended", function () { player.skip(); });
	player.song.addEventListener("play", function () {
		player.button.className = "fa-solid fa-circle-pause image";
		gdSwapSongIcon(player.song.ID, "fa-play", "fa-pause");
	});
	player.song.addEventListener("pause", function () {
		player.button.className = "fa-solid fa-circle-play image";
		gdSwapSongIcon(player.song.ID, "fa-pause", "fa-play");
	});

	player.play = function () {
		if (!player.isPlaying) return player.process();
		if (player.song.paused) player.song.play();
		else player.song.pause();
	};
	player.process = function (previous) {
		if (!player.queue.length) return false;
		if (player.isPlaying) return false;
		if (!document.querySelector("#audioPlayerButton .indicator")) {
			var indicator = document.createElement("span");
			indicator.className = "indicator";
			player.showButton.append(indicator);
		}
		player.number += previous ? -1 : 1;
		player.currentSong = player.queue[player.number];
		player.progress.value = 0;
		player.progress.update({ target: player.progress });
		player.song.src = decodeURIComponent(player.currentSong.src);
		player.name.innerHTML = player.currentSong.name;
		player.author.innerHTML = player.currentSong.author;
		player.song.ID = player.currentSong.ID;
		player.volume.value = window.localStorage.volume * 1000;
		player.volume.change(window.localStorage.volume * 1000, true);
		player.song.play();
		player.button.className = "fa-solid fa-circle-pause image";
		player.song.setCover();
		player.isPlaying = true;
		player.buttonBackward.disabled = player.number <= 0;
		player.buttonForward.disabled = player.number >= player.queue.length - 1;
		if (!previous) {
			if (player.queue.length > 1) {
				var node = document.getElementById("queue" + player.currentSong.ID);
				if (node) player.queueDiv.removeChild(node);
			}
		} else player.updateQueue(player.queue[player.number + 1], true);
	};
	player.skip = function () {
		if (player.number >= player.queue.length - 1) { player.stop(); return false; }
		player.isPlaying = false;
		gdSwapSongIcon(player.song.ID, "fa-pause", "fa-play");
		player.process();
	};
	player.previous = function () {
		if (player.number <= 0) return false;
		player.isPlaying = false;
		gdSwapSongIcon(player.song.ID, "fa-pause", "fa-play");
		player.process(true);
	};
	player.addToQueue = function (song) {
		player.queue.push(song);
		if (!player.isPlaying) player.process();
		player.buttonBackward.disabled = player.number <= 0;
		player.buttonForward.disabled = player.number >= player.queue.length - 1;
		if (player.queue.length > 1) player.updateQueue(song);
	};
	player.song.download = function () {
		if (!player.song.src) return;
		var fileName = (player.currentSong && player.currentSong.name) || player.song.name || ("song" + (player.song.ID || ""));
		if (!/\.mp3$/i.test(fileName)) fileName += ".mp3";
		fetch(player.song.src).then(function (r) {
			if (!r.ok) throw new Error("HTTP " + r.status);
			return r.blob();
		}).then(function (blob) {
			var fakeA = document.createElement("a");
			fakeA.href = URL.createObjectURL(blob);
			fakeA.download = fileName;
			document.body.appendChild(fakeA);
			fakeA.click();
			document.body.removeChild(fakeA);
			setTimeout(function () { URL.revokeObjectURL(fakeA.href); }, 4000);
		}).catch(function () { createToast((window.GDIPS && window.GDIPS.i18n && window.GDIPS.i18n.downloadFailed) || "Download failed"); });
	};
	player.song.setCover = function () {
		player.cover.src = "incl/miyuki-san-andreas.jpg";
		if (typeof player.covers[player.song.ID] !== "undefined") {
			player.cover.src = player.covers[player.song.ID];
			return;
		}
		if (typeof jsmediatags === "undefined") return;
		jsmediatags.read(player.song.src, {
			onSuccess: function (tag) {
				if (tag.tags.picture) {
					var data = tag.tags.picture.data, format = tag.tags.picture.format, base64 = "";
					for (var i = 0; i < data.length; i++) base64 += String.fromCharCode(data[i]);
					var cover = "data:" + format + ";base64," + window.btoa(base64);
					player.cover.src = cover;
					player.covers[player.song.ID] = cover;
				}
			},
			onError: function () { player.cover.src = "incl/miyuki-san-andreas.jpg"; }
		});
	};
	player.updateQueue = function (song, first) {
		var row = document.createElement("div");
		row.id = "queue" + song.ID;
		row.className = "item";
		var cover = document.createElement("div");
		cover.className = "cover";
		var playI = document.createElement("i");
		playI.className = "fa-solid fa-circle-play image";
		playI.setAttribute("onclick", "player.queueDiv.move(" + song.ID + ")");
		var img = document.createElement("img");
		img.className = "image";
		img.src = typeof player.covers[song.ID] !== "undefined" ? player.covers[song.ID] : "incl/miyuki-san-andreas.jpg";
		cover.append(playI, img);
		var names = document.createElement("div");
		names.className = "track";
		names.innerHTML = '<p class="name"></p><p class="author"></p>';
		names.querySelector(".name").innerHTML = escapeHtml(song.name);
		names.querySelector(".author").innerHTML = escapeHtml(song.author);
		var remove = document.createElement("button");
		remove.className = "buttons";
		remove.setAttribute("onclick", "player.queueDiv.remove(" + song.ID + ")");
		remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';
		names.append(remove);
		row.append(cover, names);
		if (first) player.queueDiv.prepend(row);
		else player.queueDiv.append(row);
	};
	player.queueDiv.move = function (songID) {
		if (player.queue.length <= 1) return false;
		for (var queueID in player.queue) {
			if (queueID <= player.number) continue;
			var queueSong = player.queue[queueID];
			if (queueSong.ID != songID) {
				var node = document.getElementById("queue" + queueSong.ID);
				if (node) player.queueDiv.removeChild(node);
			} else {
				player.number = queueID - 1;
				gdSwapSongIcon(player.currentSong.ID, "fa-pause", "fa-play");
				player.isPlaying = false;
				player.process();
				break;
			}
		}
	};
	player.queueDiv.remove = function (song) {
		if (player.queue.length <= 1) return;
		for (var queueID in player.queue) {
			if (queueID <= player.number) continue;
			var queueSong = player.queue[queueID];
			if (queueSong.ID == song) {
				var node = document.getElementById("queue" + queueSong.ID);
				if (node) player.queueDiv.removeChild(node);
				player.queue.splice(queueID, 1);
				player.buttonBackward.disabled = player.number <= 0;
				player.buttonForward.disabled = player.number >= player.queue.length - 1;
				return true;
			}
		}
	};
	player.stop = function () {
		var indicator = document.querySelector("#audioPlayerButton .indicator");
		if (indicator) player.showButton.removeChild(indicator);
		player.song.pause();
		player.queue.forEach(function (queueSong, queueID) {
			if (queueID <= player.number) return;
			var node = document.getElementById("queue" + queueSong.ID);
			if (node) player.queueDiv.removeChild(node);
		});
		player.queue = [];
		player.number = -1;
		var i18n = (window.GDIPS && window.GDIPS.i18n) || {};
		player.name.innerHTML = i18n.songPlaceholder || "";
		player.author.innerHTML = i18n.authorPlaceholder || "";
		player.cover.src = "incl/miyuki-san-andreas.jpg";
		player.song.ID = "";
		player.song.src = "";
		document.querySelectorAll("button i.fa-pause").forEach(function (el) {
			el.classList.replace("fa-pause", "fa-play");
		});
		delete player.currentSong;
		player.isPlaying = false;
		player.showButton.classList.remove("show");
		root.classList.remove("show");
		player.queueDiv.classList.remove("show");
	};

	/* Media keys */
	if (!window.__gdipsMediaKeys) {
		window.__gdipsMediaKeys = true;
		window.addEventListener("keydown", function (e) {
			if (!player) return;
			switch (e.key) {
				case "MediaTrackNext": player.skip(); break;
				case "MediaTrackPrevious": player.previous(); break;
				case "MediaStop": player.stop(); break;
			}
		});
	}
}

function gdSwapSongIcon(songID, from, to) {
	if (!songID) return;
	var icon = document.getElementById("icon" + songID);
	if (icon) icon.classList.replace(from, to);
}

/* ------------------------------------------------------------------ *
 *  Play button in song lists                                         *
 * ------------------------------------------------------------------ */
function btnsong(id) {
	var btn = document.getElementById("btn" + id);
	if (!btn) return;
	var title = btn.title || "";
	var song = {
		src: btn.getAttribute("download"),
		title: escapeHtml(title),
		author: escapeHtml(title.split(/( - | - )/g)[0] || ""),
		name: escapeHtml(title.split(/( - | - )/g)[2] || ""),
		ID: id
	};
	if (player && player.isPlaying) {
		if (player.currentSong && JSON.stringify(player.currentSong) === JSON.stringify(song)) player.play();
		else if (!player.queue.find(function (find) { return find.ID == song.ID; })) player.addToQueue(song);
	} else if (player) player.addToQueue(song);
}

/* ------------------------------------------------------------------ *
 *  Boot - called once on load and after every SPA navigation         *
 * ------------------------------------------------------------------ */
function gdBoot() {
	gdBindOnce();
	gdInitPlayer();
	gdInitTheme();
}
