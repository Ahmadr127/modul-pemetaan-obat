@props([
    'columns' => [],      // [['key' => 'name', 'label' => 'Nama'], ...]
    'rows' => [],         // array of associative arrays: [['name' => '...', 'email' => '...'], ...]
    'perPage' => 10,
    'perPageOptions' => [5, 10, 25, 50, 100],
    'empty' => 'Tidak ada data.',
    'searchPlaceholder' => 'Cari...',
    'actions' => [],      // [['type' => 'button|link|form', 'icon', 'label', 'event'|'url', 'method', 'confirm', 'color'], ...]
])

<div
    x-data="searchableTable({{ Js::from([
        'columns' => $columns,
        'rows' => $rows,
        'perPage' => $perPage,
        'perPageOptions' => $perPageOptions,
        'empty' => $empty,
        'searchPlaceholder' => $searchPlaceholder,
        'actions' => $actions,
        'csrfToken' => csrf_token(),
    ]) }})"
    {{ $attributes->merge(['class' => 'bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden']) }}
>
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-max">
            <thead>
                {{-- Baris pertama: judul kolom + pencarian per kolom --}}
                <tr class="bg-sp-primary/10">
                    <template x-for="col in columns" :key="col.key">
                        <th class="px-4 py-2.5 text-left align-top whitespace-nowrap">
                            <span class="block font-semibold text-sp-navy" x-text="col.label"></span>
                            <div class="relative mt-1">
                                <i class="bi bi-search absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                                <input
                                    type="text"
                                    :placeholder="searchPlaceholder"
                                    x-model="filters[col.key]"
                                    @input="page = 1"
                                    class="w-full min-w-[7.5rem] pl-6 pr-2 py-1 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors"
                                >
                            </div>
                        </th>
                    </template>
                    <template x-if="actions.length > 0">
                        <th class="px-4 py-2.5 text-left align-top whitespace-nowrap">
                            <span class="block font-semibold text-sp-navy">Aksi</span>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                <template x-for="(row, rowIndex) in pagedRows" :key="rowIndex">
                    <tr class="hover:bg-gray-50 transition-colors">
                        <template x-for="col in columns" :key="col.key">
                            <td class="px-4 py-3 whitespace-nowrap" x-text="row[col.key]"></td>
                        </template>
                        <template x-if="actions.length > 0">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center justify-start gap-1.5">
                                    <template x-for="action in actions" :key="action.label">
                                        <template x-if="action.type === 'button'">
                                            <button type="button" :title="action.label"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary transition-colors"
                                                @click="$dispatch(action.event, { id: row.id })">
                                                <i :class="'bi ' + action.icon"></i>
                                            </button>
                                        </template>
                                        <template x-if="action.type === 'link'">
                                            <a :href="resolveUrl(action.url, row)" :title="action.label"
                                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-sp-primary/10 hover:border-sp-primary/30 hover:text-sp-primary transition-colors">
                                                <i :class="'bi ' + action.icon"></i>
                                            </a>
                                        </template>
                                        <template x-if="action.type === 'form'">
                                            <form :action="resolveUrl(action.url, row)" method="POST" @submit.prevent="confirmSubmit($event, action)">
                                                <input type="hidden" name="_token" :value="csrfToken">
                                                <input type="hidden" name="_method" :value="action.method">
                                                <button type="submit" :title="action.label"
                                                    :class="action.color || 'inline-flex items-center justify-center w-7 h-7 rounded-md text-sp-navy border border-gray-200 bg-white hover:bg-red-50 hover:border-red-300 hover:text-red-600 transition-colors'">
                                                    <i :class="'bi ' + action.icon"></i>
                                                </button>
                                            </form>
                                        </template>
                                    </template>
                                </div>
                            </td>
                        </template>
                    </tr>
                </template>

                <tr x-show="filteredRows.length === 0">
                    <td :colspan="columns.length + actions.length" class="px-4 py-8 text-center text-gray-500" x-text="empty"></td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-4 py-3 border-t border-gray-100">
        <div class="flex items-center gap-2" x-show="filteredRows.length > 0">
            <label class="text-xs text-gray-500">Tampilkan</label>
            <select
                x-model.number="perPage"
                @change="page = 1"
                class="px-2 py-1 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary"
            >
                <template x-for="opt in perPageOptions" :key="opt">
                    <option :value="opt" x-text="opt"></option>
                </template>
            </select>
            <label class="text-xs text-gray-500">per halaman</label>
        </div>
        <p class="text-xs text-gray-500" x-show="filteredRows.length > 0">
            Menampilkan <span x-text="start"></span>–<span x-text="end"></span> dari <span x-text="filteredRows.length"></span> data
        </p>
        <div class="flex items-center gap-1" x-show="totalPages > 1">
            <button type="button" @click="prev()" :disabled="page <= 1"
                class="px-2 py-1 text-sm border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                <i class="bi bi-chevron-left"></i>
            </button>
            <template x-for="p in pageNumbers" :key="p">
                <button type="button" @click="go(p)"
                    :class="p === page ? 'bg-sp-primary text-white border-sp-primary' : 'bg-white text-gray-600 hover:bg-gray-50'"
                    class="px-2.5 py-1 text-sm border border-gray-300 rounded-md transition-colors"
                    x-text="p"></button>
            </template>
            <button type="button" @click="next()" :disabled="page >= totalPages"
                class="px-2 py-1 text-sm border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</div>
