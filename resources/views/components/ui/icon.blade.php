@props([
    "name",
    "size" => 18,
])
<svg
    {{ $attributes }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>
    @switch($name)
        @case("plus")
            <path d="M12 5v14M5 12h14" />

            @break
        @case("search")
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />

            @break
        @case("arrow")
            <path d="m9 18 6-6-6-6" />

            @break
        @case("folder")
            <path d="M3 6h6l2 2h10v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />

            @break
        @case("users")
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />

            @break
        @case("user-round")
            <circle cx="12" cy="8" r="4" />
            <path d="M20 21a8 8 0 0 0-16 0" />

            @break
        @case("chevron-down")
            <path d="m6 9 6 6 6-6" />

            @break
        @case("briefcase")
            <rect x="3" y="7" width="18" height="13" rx="2" />
            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18" />

            @break
        @case("file")
            <path
                d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
            />
            <path d="M14 2v6h6M8 13h8M8 17h6" />

            @break
        @case("menu")
            <path d="M4 7h16M4 12h16M4 17h16" />

            @break
        @case("trash")
            <path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6" />

            @break
        @case("logout")
            <path
                d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"
            />

            @break
        @case("building-2")
            <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18M6 12H4a2 2 0 0 0-2 2v8h20v-8a2 2 0 0 0-2-2h-2M10 6h4M10 10h4M10 14h4M10 18h4" />

            @break
        @case("pencil")
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />

            @break
        @case("briefcase-business")
            <rect width="20" height="14" x="2" y="7" rx="2" ry="2" />
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16M12 12h.01" />

            @break
        @case("file-plus-2")
            <path d="M4 22h14a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v4" />
            <path d="M14 2v6h6M3 15h6M6 12v6" />

            @break
        @case("file-text")
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path d="M14 2v6h6M8 13h8M8 17h8M8 9h2" />

            @break
        @case("factory")
            <path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 4V8l-7 4V4H2Z" />
            <path d="M17 18h1M12 18h1M7 18h1" />

            @break
        @case("map-pin")
            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" />
            <circle cx="12" cy="10" r="3" />

            @break
        @case("mail")
            <rect width="20" height="16" x="2" y="4" rx="2" />
            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />

            @break
        @case("phone")
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92Z" />

            @break
        @case("circle-check")
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <path d="m9 11 3 3L22 4" />

            @break
        @case("id-card")
            <rect width="20" height="16" x="2" y="4" rx="2" />
            <circle cx="8" cy="10" r="2" />
            <path d="M14 8h4M14 12h4M6 16c.7-1.3 1.7-2 3-2s2.3.7 3 2" />

            @break

        @case("receipt")
            <path d="M4 3h16v18l-3-2-3 2-2-2-2 2-3-2-3 2z" />
            <path d="M8 7h8M8 11h8M8 15h5" />

            @break
        @case("banknote")
            <rect x="2" y="6" width="20" height="12" rx="2" />
            <circle cx="12" cy="12" r="2.5" />
            <path d="M6 9h.01M18 15h.01" />

            @break
        @case("calculator")
            <rect x="4" y="2" width="16" height="20" rx="2" />
            <path d="M8 6h8M8 10h2M14 10h2M8 14h2M14 14h2M8 18h2M14 18h2" />

            @break
        @case("list-plus")
            <path d="M11 12H3M11 6H3M11 18H3M18 9v6M15 12h6" />

            @break
        @case("rotate-ccw")
            <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
            <path d="M3 3v5h5" />

            @break
        @case("calendar-days")
            <path d="M8 2v4M16 2v4M3 10h18" />
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01" />

            @break
        @case("clock-3")
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />

            @break
        @case("calendar-check")
            <path d="M8 2v4M16 2v4M3 10h18" />
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="m8 16 2 2 5-5" />

            @break
        @case("circle-dollar-sign")
            <circle cx="12" cy="12" r="9" />
            <path d="M16 8h-6a2 2 0 0 0 0 4h4a2 2 0 0 1 0 4H8M12 6v12" />

            @break
        @case("chart-no-axes-column-increasing")
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />

            @break
        @case("sliders-horizontal")
            <path d="M21 4h-7M10 4H3M21 12h-9M8 12H3M21 20h-5M12 20H3" />
            <path d="M14 2v4M12 10v4M16 18v4" />

            @break
        @default
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8v4M12 16h.01" />
    @endswitch
</svg>
