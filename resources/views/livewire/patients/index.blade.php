<div class="demo-page">
    <div class="demo-page-heading"><div><p class="demo-eyebrow">Registry</p><h1>Patients</h1><p class="demo-muted">Select a record to review its FHIR representation.</p></div><flux:button variant="primary" :href="route('patients.create')" icon="plus" wire:navigate>Add Patient</flux:button></div>
    <div class="demo-notice"><strong>Synthetic Test Data</strong> · All records in this registry are for demonstration only.</div>
    <section class="demo-panel overflow-hidden">
        <div class="overflow-x-auto"><table class="demo-table"><caption class="sr-only">Synthetic patient registry</caption><thead><tr><th scope="col">Patient number</th><th scope="col">Patient name</th><th scope="col">Gender</th><th scope="col">Birth date</th><th scope="col">Status</th><th scope="col"><span class="sr-only">View patient</span></th></tr></thead><tbody>
        @forelse($patients as $patient)
            <tr wire:key="patient-{{ $patient->id }}"><td class="font-mono text-xs">{{ $patient->patient_number }}</td><td class="font-medium text-slate-900">{{ $patient->first_name }} {{ $patient->last_name }}</td><td>{{ ucfirst($patient->gender) }}</td><td class="whitespace-nowrap">{{ $patient->birth_date }}</td><td><span class="demo-badge">{{ $patient->active ? 'Active' : 'Inactive' }}</span></td><td><flux:button size="sm" variant="ghost" :href="route('patients.show', $patient)" :aria-label="'View patient '.$patient->patient_number" wire:navigate>View <span aria-hidden="true">→</span></flux:button></td></tr>
        @empty
            <tr><td colspan="6" class="!py-12 text-center"><h2>No patients yet</h2><p class="mt-2 demo-muted">Add a synthetic patient to start the FHIR workflow.</p></td></tr>
        @endforelse
        </tbody></table></div>
    </section>
    {{ $patients->links() }}
</div>
