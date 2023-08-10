<template>
    <div v-if="showSearch" class="global-search-wrapper" @click="reset">
        <div class="global-search-container " v-loading="loading" @click.stop="">
            <div class="global-search-body ">
                <div class="el-input el-input--prefix">
                    <input ref="searchInput"
                           prefix-icon="el-icon-search"
                           @input="search($event.target.value)"
                           type="text" name="search"
                           :placeholder="placeholder"
                           autocomplete="off"
                    />
                    <span class="el-input__prefix"><i class="el-input__icon el-icon-search"></i></span>
                </div>

                <ul class="search-result">
	                <template v-if="this.filteredLinks.length">
		                <li
			                ref="links" v-for="(link, i) in filteredLinks"
			                :key="'link_' + i"
			                tabindex='1'
			                @keyup.enter="goToSlug($event, link)"
			                @click="goToSlug($event, link)"
		                >
			                <span>{{ link.title }}</span>
		                </li>
	                </template>
	                <li v-else>
		                <span>Search not match. Try different.</span>
	                </li>
                </ul>
            </div>
            <div>
                <ul class="search-commands">
                    <li>Esc to close</li>
                    <li>
                        Navigate
                        <i class="el-icon-bottom"></i>
                        <i class="el-icon-top"></i>
                    </li>
                    <li>Tab to focus search</li>
                    <li>Enter to Select</li>
                </ul>
            </div>
        </div>
	</div>
</template>

<script>
export default {
	name: 'global-search',
	data() {
		return {
			showSearch: false,
			placeholder: 'Search anything',
			links: [],
			filteredLinks: [],
			adminUrl: '',
			linkFocusIndex: 0,
			loading: true,
		}
	},
	methods: {
		getSearchData() {
			const url = FluentFormsGlobal.$rest.route('globalSearch')
			FluentFormsGlobal.$rest.get(url)
				.then((response) => {
					this.adminUrl = response.admin_url;
					this.links = response.links;
					this.filteredLinks = this.links.slice(0, 7);
				})
				.catch( (error)  => {
					console.log(error);
				})
				.finally(() => {
					this.loading = false;
				})
		},
		search(value) {
			this.linkFocusIndex = 0;
			if (!value) {
				this.filteredLinks = this.links.slice(0, 7);
				return;
			}
			this.filteredLinks = this.links.filter(link => link.tags.join(' ').toLowerCase().includes(value.toLowerCase()))
		},
		reset() {
			this.showSearch && (this.showSearch = false);
			this.linkFocusIndex = 0;
		},
		goToSlug($event, link) {
			const oldUrl = new URL(window.location);
			window.location.href = this.adminUrl + link.path;
			if (this.shouldReload(link, oldUrl)) {
				window.location.reload();
			}
		},
		shouldReload(link, oldUrl) {
			const url = new URL(link.path, this.adminUrl);
			const oldPage = oldUrl.searchParams.get('page');
			const newPage = url.searchParams.get('page');
			const oldComponent = oldUrl.searchParams.get('component');
			const newComponent = url.searchParams.get('component');
			const oldFormId = oldUrl.searchParams.get('form_id');
			const newFormId = url.searchParams.get('form_id');
			const oldRoute = oldUrl.searchParams.get('route');
			const newRoute = url.searchParams.get('route');

			const oldHash = oldUrl.hash;
			const newHash = url.hash;
			if (newPage !== oldPage) {
				return false;
			}
			if ((oldFormId || newFormId) && oldFormId !== newFormId) {
				return false;
			}

			if ((oldRoute || newRoute) && oldRoute === newRoute) {
				return true;
			}
			if ((oldComponent || newComponent) && oldComponent === newComponent) {
				return true;
			}
			if ((!oldComponent && !newComponent) && (oldHash || newHash) && oldHash !== newHash) {
				return true;
			}
			return false;
		},
		listener(e) {
			if (e.ctrlKey || e.metaKey && e.keyCode === 75) {
				e.preventDefault && e.preventDefault();
				if (!this.showSearch) {
					this.showSearch = true;
				} else {
					this.reset()
					return;
				}
				setTimeout(() => {
					this.$refs.searchInput && this.$refs.searchInput.focus();
				}, 500);
				if (!this.links.length) {
					this.getSearchData()
				}
			} else if (e.keyCode === 27) {
				// close on ESC button press
				e.preventDefault()
				this.reset()
			} else if (e.keyCode === 38 || e.keyCode === 40) {
				e.preventDefault();
				this.handleUpDownArrow(e);
			} else if (e.keyCode === 9) {
				// Tab key for focus input el
				e.preventDefault();
				this.$refs.searchInput && this.$refs.searchInput.focus();
				this.linkFocusIndex = 0;
			}
		},
		handleUpDownArrow(e) {
			if (this.$refs.links && Array.isArray(this.$refs.links)) {
				if (e.keyCode === 38) {
					this.linkFocusIndex -= 1;
				} else {
					this.linkFocusIndex += 1;
				}
				if (this.linkFocusIndex > this.filteredLinks.length || this.linkFocusIndex <= 0) {
					this.$refs.searchInput && this.$refs.searchInput.focus();
					this.linkFocusIndex = 0;
					return;
				}
				let $link = this.$refs.links[this.linkFocusIndex -1];
				if ($link) {
					this.$nextTick(() => {
						$link.focus();
					});
				}
			}
		}
	},
	created() {
		document.addEventListener('keydown', this.listener);
		document.addEventListener('global-search-menu-button-click',  (e) => {
			this.listener({ctrlKey: true, metaKey : true, keyCode: 75})
		})
	},
	beforeDestroy() {
		document.removeEventListener('keydown', this.listener);
	}
}
</script>


