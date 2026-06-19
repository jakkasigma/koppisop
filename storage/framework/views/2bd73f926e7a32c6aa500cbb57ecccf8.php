<?php if($paginator->hasPages()): ?>
<nav role="navigation" aria-label="<?php echo e(__('Pagination Navigation')); ?>" class="admin-pager">

    <div class="admin-pager-info">
        <?php if($paginator->firstItem()): ?>
            Menampilkan <strong><?php echo e($paginator->firstItem()); ?></strong>&ndash;<strong><?php echo e($paginator->lastItem()); ?></strong>
            dari <strong><?php echo e($paginator->total()); ?></strong> data
        <?php else: ?>
            <?php echo e($paginator->count()); ?> data
        <?php endif; ?>
    </div>

    <div class="admin-pager-nav">

        
        <?php if($paginator->onFirstPage()): ?>
            <span class="admin-pager-btn disabled" aria-disabled="true" aria-label="<?php echo e(__('pagination.previous')); ?>">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            </span>
        <?php else: ?>
            <a class="admin-pager-btn" href="<?php echo e($paginator->previousPageUrl()); ?>" rel="prev" aria-label="<?php echo e(__('pagination.previous')); ?>">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            </a>
        <?php endif; ?>

        
        <?php $__currentLoopData = $elements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $element): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(is_string($element)): ?>
                <span class="admin-pager-btn disabled" aria-disabled="true"><?php echo e($element); ?></span>
            <?php endif; ?>
            <?php if(is_array($element)): ?>
                <?php $__currentLoopData = $element; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($page == $paginator->currentPage()): ?>
                        <span class="admin-pager-btn active" aria-current="page"><?php echo e($page); ?></span>
                    <?php else: ?>
                        <a class="admin-pager-btn" href="<?php echo e($url); ?>" aria-label="<?php echo e(__('Go to page :page', ['page' => $page])); ?>"><?php echo e($page); ?></a>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        
        <?php if($paginator->hasMorePages()): ?>
            <a class="admin-pager-btn" href="<?php echo e($paginator->nextPageUrl()); ?>" rel="next" aria-label="<?php echo e(__('pagination.next')); ?>">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            </a>
        <?php else: ?>
            <span class="admin-pager-btn disabled" aria-disabled="true" aria-label="<?php echo e(__('pagination.next')); ?>">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            </span>
        <?php endif; ?>

    </div>
</nav>
<?php endif; ?>
<?php /**PATH D:\psrnl\laravel\kasir\resources\views/vendor/pagination/tailwind.blade.php ENDPATH**/ ?>