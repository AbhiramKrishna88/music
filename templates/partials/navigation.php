<?php
use \OCA\Music\Utility\HtmlUtil;
HtmlUtil::printNgTemplate('navigationitem');
?>

<div id="app-navigation" ng-controller="NavigationController">
	<ul>
		<li navigation-item text="'Albums' | translate" destination="'#'"
			title="{{ albumCountText() }}"></li>
		<li navigation-item text="'Folders' | translate" destination="'#/folders'"
			title="{{ folderCountText() }}"></li>
		<li navigation-item text="'Genres' | translate" destination="'#/genres'"
			title="{{ genresCountText() }}"></li>
		<li navigation-item text="'All tracks' | translate" destination="'#/alltracks'"
			title="{{ trackCountText() }}"></li>
		<li class="app-navigation-separator"></li>
		<li id="new-playlist" class="music-navigation-item">
			<a id="create" class="app-navigation-noclose" ng-click="startCreate()" ng-hide="showCreateForm" translate>+ New Playlist</a>
			<div class="input-container">
				<input type="text" class="new-list" maxlength="256" ng-show="showCreateForm" 
					placeholder="{{ 'New Playlist' | translate }}" ng-enter="commitCreate()" ng-model="newPlaylistName" />
			</div>
			<div class="actions" ng-show="showCreateForm">
				<button class="action icon-checkmark app-navigation-noclose"
					ng-class="{ disabled: newPlaylistName.length == 0}" ng-click="commitCreate()"></button>
				<button class="action icon-close app-navigation-noclose" ng-click="showCreateForm=false"></button>
			</div>
		</li>
		<li navigation-item
			playlist="playlist" text="playlist.name" destination="'#/playlist/' + playlist.id"
			ng-repeat="playlist in playlists"
			ui-on-drop="dropOnPlaylist($data, playlist)"
			drop-validate="allowDrop(playlist)"
			drag-hover-class="drag-hover"
			title="{{ trackCountText(playlist) }}"></li>
		<li class="music-nav-settings" ng-class="{active: $parent.currentView=='#/settings'}">
			<a class="" ng-click="navigateTo('#/settings')">
				<img class="svg" src="<?php HtmlUtil::printSvgPath('settings') ?>">
				{{ 'Settings' | translate }}
			</a>
		</li>
	</ul>
</div>