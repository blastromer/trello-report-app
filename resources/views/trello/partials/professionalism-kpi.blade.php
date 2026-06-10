@php
    $pkCategories = \App\Support\ProfessionalismKpiReference::categories();
@endphp
<style>
    .pro-kpi-wrap { margin: 22px 0; padding: 0; }
    .pro-kpi-wrap > h2 { margin-top: 0; }
    .pro-kpi-weight { font-weight: 700; margin: 12px 0 6px; color: #1f2937; }
    .pro-kpi-purpose { font-size: 0.9rem; font-style: italic; color: #4b5563; margin-bottom: 18px; }
    .pro-kpi-cat { margin: 20px 0 14px; }
    .pro-kpi-cat h3 { font-size: 1rem; margin: 0 0 8px; color: #111827; }
    .pro-kpi-def { font-size: 0.875rem; color: #374151; margin: 0 0 10px; }
    .pro-kpi-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; margin-bottom: 8px; }
    .pro-kpi-table th, .pro-kpi-table td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; vertical-align: top; }
    .pro-kpi-table th { background: #f9fafb; font-weight: 700; color: #374151; width: 3.5rem; }
    .pro-kpi-table th:last-child { width: auto; }
    .pro-kpi-total { font-weight: 700; margin-top: 16px; color: #111827; }
</style>
<div class="pro-kpi-wrap">
    <h2>{{ \App\Support\ProfessionalismKpiReference::rubricHeading() }}</h2>
    <p class="pro-kpi-weight">{{ \App\Support\ProfessionalismKpiReference::weightLine() }}</p>
    <p class="pro-kpi-purpose">{{ \App\Support\ProfessionalismKpiReference::purposeLine() }}</p>

    @foreach($pkCategories as $cat)
        <div class="pro-kpi-cat">
            <h3>{{ $cat['title'] }}</h3>
            <p class="pro-kpi-def"><strong>Definition:</strong> {{ $cat['definition'] }}</p>
            <table class="pro-kpi-table">
                <thead>
                    <tr>
                        <th>Score</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cat['rows'] as $r)
                        <tr>
                            <td>{{ $r['score'] }}</td>
                            <td>{{ $r['description'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <p class="pro-kpi-total">Total professionalism score = sum of the five categories (maximum 25).</p>
</div>
