@extends('layouts.app')
@section('title', 'Payment Modes')

@section('content')
<style>
    /* Hero Header */
    .pm-hero {
        background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
        border-radius: 1.5rem;
        padding: 2.5rem 2rem 4rem;
        color: white;
        margin-bottom: -2.5rem;
        position: relative;
        overflow: hidden;
    }
    .pm-hero::after {
        content: '';
        position: absolute;
        top: -20px; right: 10%;
        width: 200px; height: 200px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        filter: blur(20px);
    }
    
    /* Grid & Cards */
    .pm-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        position: relative;
        z-index: 10;
    }
    
    .pm-card {
        background: #fff;
        border-radius: 1.25rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .pm-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.08);
    }
    
    .pm-card-header {
        padding: 1.5rem 1.5rem 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .pm-icon {
        width: 48px;
        height: 48px;
        border-radius: 1rem;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--he-primary);
        flex-shrink: 0;
    }
    
    .pm-body {
        padding: 0 1.5rem 1.5rem;
        flex-grow: 1;
    }
    
    .pm-actions {
        border-top: 1px solid #f1f5f9;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
    }
    
    /* Add New Card */
    .pm-card-add {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 2px dashed #cbd5e1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        padding: 3rem 1.5rem;
        color: #64748b;
        transition: all 0.3s;
    }
    .pm-card-add:hover {
        border-color: var(--he-primary);
        color: var(--he-primary);
        background: #fff;
    }
    .pm-card-add i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
    }
    
    /* Inline Form Styling */
    .pm-form-inline {
        background: #fff;
        border-radius: 1.25rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        padding: 1.5rem;
        animation: formFadeIn 0.3s ease-out forwards;
    }
    @keyframes formFadeIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    .pm-form-inline .form-control {
        border-radius: 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 0.75rem 1rem;
    }
    .pm-form-inline .form-control:focus {
        background: #fff;
        border-color: var(--he-primary);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
</style>

<div x-data="paymentModes()" class="page-enter">
    
    <!-- Hero Banner -->
    <div class="pm-hero">
        <h1 class="h3 fw-bold mb-1">Payment Modes</h1>
        <p class="mb-0 opacity-75">Configure the methods available for fee collection</p>
    </div>

    <!-- Grid -->
    <div class="pm-grid">
        
        <!-- Quick Add Card (Triggers Form) -->
        <div class="pm-card pm-card-add" x-show="!isAdding" @click="isAdding = true">
            <i class="fa-solid fa-plus-circle"></i>
            <h5 class="fw-bold mb-0">Add New Mode</h5>
            <p class="small text-muted mt-1 text-center">Create a new payment method</p>
        </div>
        
        <!-- Add Form (Inline) -->
        <div class="pm-form-inline" x-show="isAdding" x-cloak>
            <form method="POST" action="{{ route('admin.payment-modes.store') }}">
                @csrf
                <div class="d-flex align-items-center mb-4">
                    <div class="pm-icon bg-primary text-white me-3"><i class="fa-solid fa-bolt"></i></div>
                    <h5 class="fw-bold mb-0">New Mode</h5>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold small">Mode Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. UPI, Bank Transfer" required x-ref="addNameInput">
                </div>
                
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="requires_reference" value="1" id="addRef">
                    <label class="form-check-label small fw-bold text-dark" for="addRef">Requires Reference No.</label>
                    <div class="form-text mt-1 text-muted small">Check this if the mode needs a UTR/Transaction ID during collection.</div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 rounded-pill fw-bold">Save Mode</button>
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="isAdding = false">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Loop existing modes -->
        @forelse($modes as $m)
        <div class="pm-card-wrapper" style="perspective: 1000px;">
            
            <!-- View State -->
            <div class="pm-card" x-show="editingId !== {{ $m->id }}">
                <div class="pm-card-header">
                    <div class="pm-icon {!! $m->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' !!}">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">{{ $m->name }}</h5>
                        <span class="badge {{ $m->is_active ? 'bg-success' : 'bg-secondary' }} rounded-pill px-2 mt-1" style="font-size: 0.65rem;">
                            {{ $m->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
                
                <div class="pm-body">
                    <div class="p-3 bg-light rounded-3 mt-2 border">
                        <div class="d-flex justify-content-between align-items-center small">
                            <span class="text-muted fw-semibold">Code:</span>
                            <span class="fw-bold text-dark">{{ $m->code }}</span>
                        </div>
                        <hr class="my-2 border-secondary opacity-10">
                        <div class="d-flex justify-content-between align-items-center small">
                            <span class="text-muted fw-semibold">Requires Reference:</span>
                            @if($m->requires_reference)
                                <span class="badge bg-warning text-dark rounded-pill"><i class="fa-solid fa-check me-1"></i>Yes</span>
                            @else
                                <span class="badge bg-light text-muted border rounded-pill">No</span>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="pm-actions">
                    <button type="button" class="btn btn-sm btn-light fw-bold text-primary rounded-pill px-3 shadow-sm border" @click="editingId = {{ $m->id }}">
                        <i class="fa-solid fa-pen me-1"></i> Edit
                    </button>
                    <form action="{{ route('admin.payment-modes.destroy', $m) }}" method="POST" data-confirm="Delete payment mode &quot;{{ $m->name }}&quot;?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-light fw-bold text-danger rounded-pill shadow-sm border">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Edit Form (Inline) -->
            <div class="pm-form-inline" x-show="editingId === {{ $m->id }}" x-cloak>
                <form method="POST" action="{{ route('admin.payment-modes.update', $m) }}">
                    @csrf @method('PUT')
                    <div class="d-flex align-items-center mb-3">
                        <div class="pm-icon bg-warning text-dark me-3"><i class="fa-solid fa-pen"></i></div>
                        <h5 class="fw-bold mb-0">Edit Mode</h5>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Mode Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ $m->name }}" required>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="requires_reference" value="1" id="editRef{{ $m->id }}" {{ $m->requires_reference ? 'checked' : '' }}>
                        <label class="form-check-label small fw-bold text-dark" for="editRef{{ $m->id }}">Requires Reference No.</label>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="editAct{{ $m->id }}" {{ $m->is_active ? 'checked' : '' }}>
                        <label class="form-check-label small fw-bold text-dark" for="editAct{{ $m->id }}">Is Active</label>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-pill fw-bold">Update</button>
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" @click="editingId = null">Cancel</button>
                    </div>
                </form>
            </div>
            
        </div>
        @empty
            @if(count($modes) === 0)
                <div class="col-span-full">
                    <div class="alert alert-info border-info-subtle rounded-4 py-3">
                        <i class="fa-solid fa-info-circle me-2"></i> No payment modes have been created yet. Click "Add New Mode" to start!
                    </div>
                </div>
            @endif
        @endforelse
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('paymentModes', () => ({
        isAdding: false,
        editingId: null,
        
        init() {
            this.$watch('isAdding', val => {
                if(val) {
                    this.editingId = null;
                    this.$nextTick(() => { this.$refs.addNameInput.focus(); });
                }
            });
            this.$watch('editingId', val => {
                if(val !== null) this.isAdding = false;
            });
        }
    }));
});
</script>
@endpush
@endsection
