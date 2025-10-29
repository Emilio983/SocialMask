<!-- Global Search Component -->
<div class="relative" x-data="globalSearch()">
    <!-- Search Input -->
    <div class="relative">
        <input
            type="text"
            x-model="query"
            @input.debounce.300ms="search()"
            @focus="showResults = true"
            placeholder="Buscar en thesocialmask..."
            class="w-64 bg-brand-bg-secondary border border-brand-border rounded-lg pl-10 pr-4 py-2 text-sm text-brand-text-primary focus:outline-none focus:border-brand-accent transition-colors"
        >
        <svg class="w-4 h-4 absolute left-3 top-2.5 text-brand-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
    </div>

    <!-- Search Results Dropdown -->
    <div
        x-show="showResults && query.length > 0"
        @click.away="showResults = false"
        x-transition
        class="absolute top-full mt-2 w-[600px] right-0 bg-brand-bg-secondary border border-brand-border rounded-xl shadow-2xl max-h-[80vh] overflow-y-auto z-50"
        style="display: none;"
    >
        <!-- Loading State -->
        <div x-show="loading" class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-2 border-brand-accent border-t-transparent"></div>
            <p class="text-sm text-brand-text-secondary mt-2">Buscando...</p>
        </div>

        <!-- Results -->
        <div x-show="!loading && totalResults > 0">
            <!-- Communities -->
            <div x-show="results.communities && results.communities.length > 0" class="border-b border-brand-border">
                <div class="px-4 py-3 bg-brand-bg-primary">
                    <h3 class="font-semibold text-sm">Comunidades</h3>
                </div>
                <div class="p-2">
                    <template x-for="community in results.communities" :key="community.id">
                        <a
                            :href="'pages/community_view.php?id=' + community.id"
                            class="flex items-center gap-3 p-3 rounded-lg hover:bg-brand-bg-primary transition-colors"
                        >
                            <img
                                :src="community.logo_url || 'https://via.placeholder.com/40'"
                                class="w-10 h-10 rounded-full object-cover"
                            >
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold truncate" x-text="community.name"></p>
                                <p class="text-xs text-brand-text-secondary truncate" x-text="community.description"></p>
                            </div>
                            <div class="text-xs text-brand-text-secondary">
                                <span x-text="community.member_count"></span> miembros
                            </div>
                        </a>
                    </template>
                </div>
                <div x-show="hasMore.communities" class="px-4 py-2">
                    <button
                        @click="loadMore('communities')"
                        class="text-xs text-brand-accent hover:underline"
                    >
                        Ver más comunidades →
                    </button>
                </div>
            </div>

            <!-- Users -->
            <div x-show="results.users && results.users.length > 0" class="border-b border-brand-border">
                <div class="px-4 py-3 bg-brand-bg-primary">
                    <h3 class="font-semibold text-sm">Usuarios</h3>
                </div>
                <div class="p-2">
                    <template x-for="user in results.users" :key="user.user_id">
                        <a
                            :href="'pages/profile.php?user_id=' + user.user_id"
                            class="flex items-center gap-3 p-3 rounded-lg hover:bg-brand-bg-primary transition-colors"
                        >
                            <img
                                :src="user.profile_image || `https://ui-avatars.com/api/?name=${user.username}&size=40`"
                                class="w-10 h-10 rounded-full object-cover"
                            >
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold truncate">
                                    <span x-text="user.username"></span>
                                    <span x-show="user.verified" class="text-brand-accent text-xs">✓</span>
                                </p>
                                <p class="text-xs text-brand-text-secondary truncate" x-text="user.display_name"></p>
                            </div>
                            <div class="text-xs text-brand-text-secondary">
                                <span x-text="user.followers_count"></span> seguidores
                            </div>
                        </a>
                    </template>
                </div>
                <div x-show="hasMore.users" class="px-4 py-2">
                    <button
                        @click="loadMore('users')"
                        class="text-xs text-brand-accent hover:underline"
                    >
                        Ver más usuarios →
                    </button>
                </div>
            </div>

            <!-- Posts -->
            <div x-show="results.posts && results.posts.length > 0">
                <div class="px-4 py-3 bg-brand-bg-primary">
                    <h3 class="font-semibold text-sm">Posts</h3>
                </div>
                <div class="p-2">
                    <template x-for="post in results.posts" :key="post.id">
                        <a
                            :href="'pages/post_view.php?id=' + post.id"
                            class="block p-3 rounded-lg hover:bg-brand-bg-primary transition-colors"
                        >
                            <div class="flex items-start gap-3">
                                <img
                                    :src="post.author.profile_image || `https://ui-avatars.com/api/?name=${post.author.username}&size=32`"
                                    class="w-8 h-8 rounded-full object-cover"
                                >
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-sm" x-text="post.author.username"></p>
                                    <p class="text-sm text-brand-text-primary line-clamp-2 mt-1" x-text="post.content"></p>
                                    <div class="flex gap-4 mt-2 text-xs text-brand-text-secondary">
                                        <span>❤️ <span x-text="post.like_count"></span></span>
                                        <span>💬 <span x-text="post.comment_count"></span></span>
                                        <span x-show="post.community" x-text="'en ' + post.community?.name"></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </template>
                </div>
                <div x-show="hasMore.posts" class="px-4 py-2">
                    <button
                        @click="loadMore('posts')"
                        class="text-xs text-brand-accent hover:underline"
                    >
                        Ver más posts →
                    </button>
                </div>
            </div>
        </div>

        <!-- No Results -->
        <div x-show="!loading && totalResults === 0 && query.length > 0" class="p-8 text-center">
            <svg class="w-12 h-12 text-brand-text-secondary mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-brand-text-secondary">No se encontraron resultados para "<span x-text="query"></span>"</p>
        </div>
    </div>
</div>

<script>
function globalSearch() {
    return {
        query: '',
        results: {
            communities: [],
            users: [],
            posts: []
        },
        hasMore: {
            communities: false,
            users: false,
            posts: false
        },
        totalResults: 0,
        loading: false,
        showResults: false,

        async search() {
            if (this.query.length < 2) {
                this.results = { communities: [], users: [], posts: [] };
                this.totalResults = 0;
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(`../api/global_search.php?q=${encodeURIComponent(this.query)}&limit=5`);
                const data = await response.json();

                if (data.success) {
                    this.results = data.results;
                    this.hasMore = data.has_more;
                    this.totalResults = data.total_results;
                }
            } catch (error) {
                console.error('Search error:', error);
            } finally {
                this.loading = false;
            }
        },

        async loadMore(type) {
            this.loading = true;

            try {
                const response = await fetch(`../api/global_search.php?q=${encodeURIComponent(this.query)}&type=${type}&limit=20`);
                const data = await response.json();

                if (data.success) {
                    this.results[type] = data.results[type];
                    this.hasMore[type] = data.has_more[type];
                }
            } catch (error) {
                console.error('Load more error:', error);
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
