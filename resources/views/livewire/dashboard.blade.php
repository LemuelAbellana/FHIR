<div class="demo-page">
    <div class="demo-page-heading">
        <div><p class="demo-eyebrow">Overview</p><h1>Dashboard</h1><p class="demo-muted">Patient records and FHIR transmission activity.</p></div>
        <flux:button variant="primary" :href="route('patients.create')" icon="plus" wire:navigate>Add Patient</flux:button>
    </div>
    <div class="demo-notice"><strong>Synthetic / test data only.</strong> This demo sends records to an external FHIR test server. Never enter real patient information.</div>
    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="demo-stat"><dt>Total Patients</dt><dd>{{ $patients }}</dd><p>Stored in the patient registry</p></div>
        <div class="demo-stat"><dt>Successful FHIR Sends</dt><dd>{{ $successful }}</dd><p>Completed HTTPS transmissions</p></div>
        <div class="demo-stat"><dt>Failed FHIR Sends</dt><dd>{{ $failed }}</dd><p>Recorded for review in audit logs</p></div>
        <div class="demo-stat"><dt>Latest Transmission</dt><dd class="!text-lg">{{ $latest?->created_at?->format('M j, H:i') ?? 'No transmissions' }}</dd><p>{{ $latest ? ucfirst($latest->result) : 'Send a patient to get started' }}</p></div>
    </dl>
    <section class="demo-panel">
        <div class="demo-section-heading"><div><h2>Recent patients</h2><p class="demo-muted">Synthetic records ready for FHIR export.</p></div><flux:button :href="route('patients.index')" variant="ghost" icon-trailing="arrow-right" wire:navigate>View registry</flux:button></div>
        <div class="divide-y divide-slate-200">
            @forelse($recentPatients as $patient)
                <a class="flex min-h-18 items-center justify-between gap-4 px-6 py-4 hover:bg-slate-50" href="{{ route('patients.show', $patient) }}" wire:navigate wire:key="patient-{{ $patient->id }}">
                    <div><p class="font-medium text-slate-900">{{ $patient->first_name }} {{ $patient->last_name }}</p><p class="demo-muted font-mono">{{ $patient->patient_number }}</p></div><span class="demo-badge">{{ $patient->active ? 'Active' : 'Inactive' }}</span>
                </a>
            @empty
                <p class="p-6 text-slate-600">No patients yet. Add a synthetic patient to begin.</p>
            @endforelse
        </div>
    </section>
    <section class="demo-panel p-6"><h2>A simple interoperability workflow</h2><ol class="mt-4 grid gap-4 text-sm text-slate-600 md:grid-cols-3"><li><strong class="block text-slate-900">01 · Select a patient</strong>Read the current database record.</li><li><strong class="block text-slate-900">02 · Review FHIR JSON</strong>Preview or download the R4 Patient resource.</li><li><strong class="block text-slate-900">03 · Send and review</strong>Transmit over HTTPS and inspect the audit events.</li></ol></section>
</div>
