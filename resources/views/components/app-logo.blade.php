@props(['sidebar' => false])
<a {{ $attributes->class('flex items-center gap-3 text-slate-900') }}>
    <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-teal-700 text-white"><x-app-logo-icon class="size-5" /></span>
    <span class="text-sm font-semibold leading-tight">FHIR Patient Demo<span class="mt-0.5 block text-xs font-normal text-slate-500">Patient interoperability</span></span>
</a>
